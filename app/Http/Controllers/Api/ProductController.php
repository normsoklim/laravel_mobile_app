<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{

    private function rules(bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'price' => [$required, 'numeric', 'min:0'],
            // Image URLs (e.g. Firebase Storage) can be long, hence max:2048.
            'image' => [$required, 'string', 'max:2048'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:2048'],
            'description' => ['nullable', 'string'],
            'sold_count' => ['nullable', 'integer', 'min:0'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['string', 'max:10'],
            'colors' => ['nullable', 'array'],
            // Hex color codes, e.g. "#FF5733" (3 or 6 digits).
            'colors.*' => ['string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'stock' => ['nullable', 'integer', 'min:0'],
            // Optional per-locale translations sent as an array:
            // "translations": [
            //   { "locale": "km", "name": "...", "description": "..." },
            //   { "locale": "en", "name": "...", "description": "..." }
            // ]
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required_with:translations', 'string', 'size:2'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
        ];
    }

    private function validatedData(Request $request, bool $creating = true): array
    {
        return collect($request->validate($this->rules($creating)))
            ->filter(fn ($value) => $value !== null)
            ->all();
    }

    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'sort' => [
                'nullable',
                Rule::in(['newest', 'price_asc', 'price_desc', 'rating', 'name']),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // withCount/withAvg eager-load the review count and
        // average rating so the appended review_count and
        // rating attributes stay cheap.
        $query = Product::query()
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->with('translations');

        if ($search = $request->query('search')) {
            $term = strtolower($search);

            $query->where(function ($q) use ($term) {
                // Search the base product name/description (English fallback).
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$term}%"])
                    // Also search across all product translations.
                    ->orWhereHas('translations', function ($tq) use ($term) {
                        $tq->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(description) LIKE ?', ["%{$term}%"]);
                    });
            });
        }

        if ($category = $request->query('category')) {
            // The products table stores category_id (the old string
            // `category` column was dropped), so resolve the name
            // to its id first.
            $categoryModel = Category::where('name', $category)->first();
            if ($categoryModel) {
                $query->where('category_id', $categoryModel->id);
            }
        }

        match ($request->query('sort')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            // NULLS LAST so products without reviews (NULL avg)
            // sort below rated ones — PostgreSQL puts NULLs first on DESC.
            'rating' => $query->orderByRaw('reviews_avg_rating DESC NULLS LAST'),
            'name' => $query->orderBy('name'),
            default => $query->latest(), // newest first
        };

        $paginator = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'message' => 'success',
            'products' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * POST /api/products
     */
    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        // Extract translations before creating the product.
        $translations = $data['translations'] ?? [];
        unset($data['translations']);

        $product = Product::create($data);

        // Save each locale translation.
        foreach ($translations as $translation) {
            ProductTranslation::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'locale' => $translation['locale'],
                ],
                [
                    'name' => $translation['name'] ?? null,
                    'description' => $translation['description'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product->load('translations'),
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/products/{id}
     */
    public function show($id)
    {
        $product = Product::with(['reviews.user', 'translations'])->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'success',
            'product' => $product,
        ]);
    }

    /**
     * GET /api/products/{id}/reviews
     * Reviews for a product, newest first, with the reviewer's
     * name and avatar so the product detail screen can render them.
     */
    public function reviews($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $reviews = $product->reviews()
            ->with('user')
            ->latest()
            ->paginate(20);

        return response()->json([
            'message' => 'success',
            'reviews' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * PUT|PATCH /api/products/{id}
     * Partial updates are supported — only the sent fields are updated.
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $this->validatedData($request, creating: false);

        // Extract translations before updating the product.
        $translations = $data['translations'] ?? [];
        unset($data['translations']);

        $product->update($data);

        // Upsert each locale translation.
        foreach ($translations as $translation) {
            ProductTranslation::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'locale' => $translation['locale'],
                ],
                [
                    'name' => $translation['name'] ?? null,
                    'description' => $translation['description'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product->fresh()->load('translations'),
        ]);
    }

    /**
     * DELETE /api/products/{id}
     */
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
