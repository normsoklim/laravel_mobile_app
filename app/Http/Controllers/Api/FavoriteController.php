<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    /**
     * GET /api/favorites
     * Return the authenticated user's favorites with products loaded.
     */
    public function index(Request $request)
    {
        $favorites = Favorite::where('user_id', $request->user()->id)
            ->with('product')
            ->get();

        return response()->json([
            'message' => 'success',
            'favorites' => $favorites,
        ]);
    }

    /**
     * POST /api/favorites
     * Add a product to favorites (merging quantity if already present).
     */
    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $quantity = (int) ($data['quantity'] ?? 1);

        try {
            $result = DB::transaction(function () use ($request, $data, $quantity) {
                $favorite = Favorite::where('user_id', $request->user()->id)
                    ->where('product_id', $data['product_id'])
                    ->lockForUpdate()
                    ->first();

                if ($favorite) {
                    // Same product favorited again → merge quantities.
                    $favorite->increment('quantity', $quantity);
                } else {
                    Favorite::create([
                        'user_id' => $request->user()->id,
                        'product_id' => $data['product_id'],
                        'quantity' => $quantity,
                    ]);
                }

                return null;
            });

            if ($result !== null) {
                return response()->json([
                    'message' => $result['error'],
                ], $result['status']);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add to favorites.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Added to favorites.',
            'favorites' => Favorite::where('user_id', $request->user()->id)
                ->with('product')
                ->get(),
        ], Response::HTTP_CREATED);
    }

    /**
     * PATCH /api/favorites/{productId}/increase
     * Increment the favorite quantity by one.
     */
    public function increase(Request $request, $productId)
    {
        $favorite = Favorite::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if (!$favorite) {
            return response()->json([
                'message' => 'Item not found in favorites.',
            ], Response::HTTP_NOT_FOUND);
        }

        $favorite->increment('quantity');

        return response()->json([
            'message' => 'Favorite quantity increased.',
            'favorites' => Favorite::where('user_id', $request->user()->id)
                ->with('product')
                ->get(),
        ]);
    }

    /**
     * PATCH /api/favorites/{productId}/decrease
     * Decrement the favorite quantity by one; the entry is removed at zero.
     */
    public function decrease(Request $request, $productId)
    {
        $favorite = Favorite::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if (!$favorite) {
            return response()->json([
                'message' => 'Item not found in favorites.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($favorite->quantity > 1) {
            $favorite->decrement('quantity');
        } else {
            $favorite->delete();
        }

        return response()->json([
            'message' => 'Favorite quantity decreased.',
            'favorites' => Favorite::where('user_id', $request->user()->id)
                ->with('product')
                ->get(),
        ]);
    }

    /**
     * PATCH /api/favorites/{productId}/quantity
     * Set an explicit quantity for a favorite entry.
     *
     * If the quantity is 0 (or below) the entry is removed entirely.
     */
    public function setQuantity(Request $request, $productId)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $favorite = Favorite::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if (!$favorite) {
            return response()->json([
                'message' => 'Item not found in favorites.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($data['quantity'] === 0) {
            $favorite->delete();
        } else {
            $favorite->update(['quantity' => $data['quantity']]);
        }

        return response()->json([
            'message' => 'Favorite quantity updated.',
            'favorites' => Favorite::where('user_id', $request->user()->id)
                ->with('product')
                ->get(),
        ]);
    }

    /**
     * DELETE /api/favorites/{productId}
     * Remove a single entry from favorites.
     */
    public function remove(Request $request, $productId)
    {
        $deleted = Favorite::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Item not found in favorites.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'Item removed from favorites.',
            'favorites' => Favorite::where('user_id', $request->user()->id)
                ->with('product')
                ->get(),
        ]);
    }

    /**
     * DELETE /api/favorites
     * Clear all favorites.
     */
    public function clear(Request $request)
    {
        Favorite::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Favorites cleared.',
            'favorites' => [],
        ]);
    }
}
