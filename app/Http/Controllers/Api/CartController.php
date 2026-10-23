<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * GET /api/cart
     * Return the authenticated user's cart with items + products loaded.
     */
    public function show(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'cart' => $this->freshCart($request),
        ]);
    }

    /**
     * POST /api/cart/items
     * Add a product to the cart (merging quantity if already present).
     *
     * Wrapped in a DB transaction so the stock check + write is atomic,
     * preventing race conditions when the same product is added concurrently.
     */
    public function addItem(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'size' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
        ]);

        $cart = $this->freshCart($request);
        $product = Product::find($data['product_id']);
        $quantity = (int) ($data['quantity'] ?? 1);

        try {
            $result = DB::transaction(function () use ($cart, $product, $quantity, $data) {
                // Lock the cart row so concurrent adds are serialised.
                $cart = Cart::where('id', $cart->id)->lockForUpdate()->first();

                $item = $cart->items()->where('product_id', $product->id)->lockForUpdate()->first();

                if ($item) {
                    // Same product added again → merge quantities.
                    $newQuantity = $item->quantity + $quantity;

                    if ($product->stock < $newQuantity) {
                        return [
                            'error' => "Insufficient stock for \"{$product->name}\" (available: {$product->stock}).",
                            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                        ];
                    }

                    $item->update(['quantity' => $newQuantity]);
                } else {
                    if ($product->stock < $quantity) {
                        return [
                            'error' => "Insufficient stock for \"{$product->name}\" (available: {$product->stock}).",
                            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                        ];
                    }

                    $cart->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'size' => $data['size'] ?? null,
                        'color' => $data['color'] ?? null,
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
                'message' => 'Failed to add item to cart.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Added to cart.',
            'cart' => $this->freshCart($request),
        ], Response::HTTP_CREATED);
    }

    /**
     * PATCH /api/cart/items/{productId}/increase
     * Increment the quantity of the line by one.
     */
    public function increaseQuantity(Request $request, $productId)
    {
        $cart = $this->freshCart($request);
        $item = $cart->items()->where('product_id', $productId)->first();

        if (!$item) {
            return response()->json([
                'message' => 'Item not found in cart.',
            ], Response::HTTP_NOT_FOUND);
        }

        $product = $item->product;

        if ($product->stock < $item->quantity + 1) {
            return response()->json([
                'message' => "Insufficient stock for \"{$product->name}\" (available: {$product->stock}).",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item->increment('quantity');

        return response()->json([
            'message' => 'Quantity increased.',
            'cart' => $this->freshCart($request),
        ]);
    }

    /**
     * PATCH /api/cart/items/{productId}/decrease
     * Decrement the quantity by one; the line is removed at zero
     * (matches the Flutter decreaseQuantity behavior).
     */
    public function decreaseQuantity(Request $request, $productId)
    {
        $cart = $this->freshCart($request);
        $item = $cart->items()->where('product_id', $productId)->first();

        if (!$item) {
            return response()->json([
                'message' => 'Item not found in cart.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($item->quantity > 1) {
            $item->decrement('quantity');
        } else {
            $item->delete();
        }

        return response()->json([
            'message' => 'Quantity decreased.',
            'cart' => $this->freshCart($request),
        ]);
    }

    /**
     * PATCH /api/cart/items/{productId}/quantity
     * Set an explicit quantity for a cart line.
     *
     * If the quantity is 0 (or below) the line is removed entirely,
     * matching the Flutter CartProvider.setQuantity behavior.
     */
    public function setQuantity(Request $request, $productId)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $cart = $this->freshCart($request);
        $item = $cart->items()->where('product_id', $productId)->first();

        if (!$item) {
            return response()->json([
                'message' => 'Item not found in cart.',
            ], Response::HTTP_NOT_FOUND);
        }

        $product = $item->product;

        if ($data['quantity'] === 0) {
            $item->delete();
        } else {
            if ($product->stock < $data['quantity']) {
                return response()->json([
                    'message' => "Insufficient stock for \"{$product->name}\" (available: {$product->stock}).",
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $item->update(['quantity' => $data['quantity']]);
        }

        return response()->json([
            'message' => 'Quantity updated.',
            'cart' => $this->freshCart($request),
        ]);
    }

    /**
     * PATCH /api/cart/items/{productId}
     * Update the size and/or color of a cart line.
     */
    public function updateItem(Request $request, $productId)
    {
        $data = $request->validate([
            'size' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
        ]);

        $cart = $this->freshCart($request);
        $item = $cart->items()->where('product_id', $productId)->first();

        if (!$item) {
            return response()->json([
                'message' => 'Item not found in cart.',
            ], Response::HTTP_NOT_FOUND);
        }

        $item->update([
            'size' => array_key_exists('size', $data) ? $data['size'] : $item->size,
            'color' => array_key_exists('color', $data) ? $data['color'] : $item->color,
        ]);

        return response()->json([
            'message' => 'Item updated.',
            'cart' => $this->freshCart($request),
        ]);
    }

    /**
     * DELETE /api/cart/items/{productId}
     * Remove a single line from the cart.
     */
    public function removeItem(Request $request, $productId)
    {
        $cart = $this->freshCart($request);

        $deleted = $cart->items()->where('product_id', $productId)->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Item not found in cart.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'Item removed from cart.',
            'cart' => $this->freshCart($request),
        ]);
    }

    /**
     * DELETE /api/cart
     * Empty the cart.
     */
    public function clear(Request $request)
    {
        $cart = $this->freshCart($request);

        $cart->items()->delete();

        return response()->json([
            'message' => 'Cart cleared.',
            'cart' => $this->freshCart($request),
        ]);
    }

    /**
     * Get (or lazily create) the user's cart with items and products loaded.
     */
    private function freshCart(Request $request): Cart
    {
        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return $cart->load('items.product');
    }
}