<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{

    private function rules(bool $creating = true, ?int $ignoreId = null): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [
                $required,
                'string',
                'max:255',
                // Names are unique (they also key the products.category link).
                $ignoreId
                    ? Rule::unique('categories', 'name')->ignore($ignoreId)
                    : Rule::unique('categories', 'name'),
            ],
            // Icon asset path, e.g. "images/svg/clothes.svg".
            'icon' => ['nullable', 'string', 'max:2048'],
            'icon_url' => ['nullable', 'string', 'max:2048'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required_with:translations', 'string', 'size:2'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * GET /api/categories
     * List categories with product counts. The payload maps 1:1 to the
     * Flutter `Category` model: `translated_name` -> name, `icon` /
     * `icon_url` -> icon.
     */
    public function index()
    {
        $categories = Category::query()
            ->withCount('products')
            ->with('translations')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'success',
            'categories' => $categories,
        ]);
    }

    /**
     * POST /api/categories
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        // Extract translations before creating the category.
        $translations = $data['translations'] ?? [];
        unset($data['translations']);

        $category = Category::create($data);

        // Save each locale translation.
        foreach ($translations as $translation) {
            CategoryTranslation::updateOrCreate(
                [
                    'category_id' => $category->id,
                    'locale' => $translation['locale'],
                ],
                [
                    'name' => $translation['name'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Category created successfully.',
            'category' => $category->load('translations'),
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/categories/{id}
     */
    public function show($id)
    {
        $category = Category::withCount('products')
            ->with('translations')
            ->find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'success',
            'category' => $category,
        ]);
    }

    /**
     * PUT|PATCH /api/categories/{id}
     * Partial updates are supported — only the sent fields are updated.
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = collect($request->validate(
            $this->rules(creating: false, ignoreId: $category->id)
        ))->filter(fn ($value) => $value !== null)->all();

        // Extract translations before updating the category.
        $translations = $data['translations'] ?? [];
        unset($data['translations']);

        $category->update($data);

        // Upsert each locale translation.
        foreach ($translations as $translation) {
            CategoryTranslation::updateOrCreate(
                [
                    'category_id' => $category->id,
                    'locale' => $translation['locale'],
                ],
                [
                    'name' => $translation['name'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Category updated successfully.',
            'category' => $category->fresh()->load('translations'),
        ]);
    }

    /**
     * DELETE /api/categories/{id}
     * Deletion is blocked while products still reference the category,
     * so no product is left pointing at a missing category name.
     */
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this category because products still reference it. Reassign or delete those products first.',
            ], Response::HTTP_CONFLICT);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
