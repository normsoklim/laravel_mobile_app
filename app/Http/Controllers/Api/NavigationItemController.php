<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NavigationItem;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Bottom-navigation items (icons, labels, order) stored in PostgreSQL.
 *
 * The icon *files* are hosted in Cloudinary; this controller only manages the
 * URLs and the metadata the Flutter app needs to render the navigation bar.
 */
class NavigationItemController extends Controller
{
    /**
     * Validation rules shared by create/update.
     */
    private function rules(bool $creating = true, ?int $ignoreId = null): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'key' => [
                $required,
                'string',
                'max:50',
                $ignoreId
                    ? Rule::unique('navigation_items', 'key')->ignore($ignoreId)
                    : Rule::unique('navigation_items', 'key'),
            ],
            'label' => [$required, 'string', 'max:100'],
            'label_km' => ['nullable', 'string', 'max:100'],
            // Cloudinary URL (or a bundled asset path) of the default icon.
            'icon_url' => [$required, 'string', 'max:2048'],
            // Optional Cloudinary URL of the selected/active icon.
            'active_icon_url' => ['nullable', 'string', 'max:2048'],
            // Approved Flutter screen identifier, e.g. "home" / "wishlist".
            'route_key' => [$required, 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * GET /api/navigation-items
     *
     * Public read: the Flutter bottom-navigation bar is built from this list.
     * Only the visible items are returned, ordered by `sort_order`.
     */
    public function index()
    {
        $items = NavigationItem::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'message' => 'success',
            'navigation_items' => $items,
        ]);
    }

    /**
     * GET /api/navigation-items/{id}
     */
    public function show($id)
    {
        $item = NavigationItem::find($id);

        if (! $item) {
            return response()->json([
                'message' => 'Navigation item not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'success',
            'navigation_item' => $item,
        ]);
    }

    /**
     * POST /api/navigation-items
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $item = NavigationItem::create($data);

        return response()->json([
            'message' => 'Navigation item created successfully.',
            'navigation_item' => $item,
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT|PATCH /api/navigation-items/{id}
     *
     * Partial updates are supported — only the sent fields are updated.
     */
    public function update(Request $request, $id)
    {
        $item = NavigationItem::find($id);

        if (! $item) {
            return response()->json([
                'message' => 'Navigation item not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = collect($request->validate(
            $this->rules(creating: false, ignoreId: $item->id)
        ))->filter(fn ($value) => $value !== null)->all();

        $item->update($data);

        return response()->json([
            'message' => 'Navigation item updated successfully.',
            'navigation_item' => $item->fresh(),
        ]);
    }

    /**
     * DELETE /api/navigation-items/{id}
     */
    public function destroy($id)
    {
        $item = NavigationItem::find($id);

        if (! $item) {
            return response()->json([
                'message' => 'Navigation item not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $item->delete();

        return response()->json([
            'message' => 'Navigation item deleted successfully.',
        ]);
    }

    /**
     * POST /api/navigation-items/{id}/icon
     *
     * Uploads a PNG/SVG icon to Cloudinary and stores the resulting URL on the
     * navigation item. Use `variant=active` to replace the active icon.
     */
    public function uploadIcon(Request $request, $id, CloudinaryService $cloudinary)
    {
        $item = NavigationItem::find($id);

        if (! $item) {
            return response()->json([
                'message' => 'Navigation item not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $request->validate([
            'icon' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'variant' => ['nullable', Rule::in(['icon', 'active_icon'])],
        ]);

        $uploadedFile = $request->file('icon');
        $variant = $request->input('variant', 'icon');

        // Upload to Cloudinary and store the secure URL on the record.
        // Pass the real temp file path (string) — the raw cloudinary_php SDK
        // does not accept a Laravel UploadedFile object directly.
        $iconUrl = $cloudinary->uploadAndGetUrl(
            $uploadedFile->getRealPath(),
            [],
            'tinh_shop/navigation'
        );

        $item->update([
            $variant === 'active_icon' ? 'active_icon_url' : 'icon_url' => $iconUrl,
        ]);

        return response()->json([
            'message' => 'Icon uploaded successfully.',
            'navigation_item' => $item->fresh(),
        ]);
    }
}
