<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\OrderStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * Status values used by the Flutter app (order_screen.dart filters
     * on "Pending"/"In Delivery" and "Completed"/"Delivered").
     */
    private const STATUSES = [
        'Pending', 'In Delivery', 'Completed', 'Delivered', 'Cancelled',
    ];

    /**
     * Payment methods offered by the Flutter profile payment
     * screen (PaymentMethod.typeLabel values).
     */
    private const PAYMENT_METHODS = ['E-Wallet', 'Card', 'KHQR'];

    /**
     * GET /api/orders
     * List the authenticated user's orders (newest first) with items.
     * Optional query param: status.
     */
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        $orders = $request->user()
            ->orders()
            ->with('items')
            ->when(
                $request->query('status'),
                fn ($q, $status) => $q->where('status', $status)
            )
            ->latest()
            ->get();

        return response()->json([
            'message' => 'success',
            'orders' => $orders,
        ]);
    }

    /**
     * POST /api/orders — checkout.
     * The client only sends product ids, quantities, size/color, address
     * and payment method. Prices, names and images are snapshotted from
     * the database server-side so totals can't be tampered with.
     *
     * The optional latitude/longitude are the map pin the customer
     * dropped in the address picker. They are snapshotted on the order
     * so the driver and the live-tracking map know WHERE the order
     * has to be delivered (see the deliveries table).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.size' => ['nullable', 'string', 'max:10'],
            'items.*.color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $deliveryFee = round((float) ($data['delivery_fee'] ?? 0), 2);
        $discount = round((float) ($data['discount'] ?? 0), 2);

        // Load all ordered products in one query.
        $products = Product::whereIn('id', collect($data['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $subtotal = 0;
        $lines = [];

        foreach ($data['items'] as $item) {
            $product = $products[$item['product_id']] ?? null;

            if (!$product) {
                return response()->json([
                    'message' => "Product {$item['product_id']} not found.",
                ], Response::HTTP_NOT_FOUND);
            }

            if ($product->stock < $item['quantity']) {
                return response()->json([
                    'message' => "Insufficient stock for \"{$product->name}\" (available: {$product->stock}).",
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $subtotal += $product->price * $item['quantity'];

            $lines[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'image' => $product->image,
                'size' => $item['size'] ?? null,
                'color' => $item['color'] ?? null,
                'quantity' => (int) $item['quantity'],
                'price' => $product->price,
            ];
        }

        $subtotal = round($subtotal, 2);
        $total = round($subtotal + $deliveryFee - $discount, 2);

        $order = DB::transaction(function () use ($request, $data, $lines, $subtotal, $deliveryFee, $discount, $total) {
            $order = Order::create([
                'order_number' => 'ORD-'.((int) (microtime(true) * 1000)).'-'.strtoupper(Str::random(4)),
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount' => $discount,
                'total' => $total,
                'address' => $data['address'],
                // The delivery destination on the map (null when the
                // customer typed the address manually).
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'payment_method' => $data['payment_method'],
                'status' => 'Pending',
            ]);

            $order->items()->createMany($lines);

            // Record the initial status so the tracking timeline
            // starts from the moment the order was placed.
            $order->trackings()->create([
                'status' => 'Pending',
                'description' => 'Order is in Packing',
                'location' => $data['address'],
                'tracking_date' => now()->toDateString(),
                'tracking_time' => now(),
                'is_completed' => false,
            ]);

            // Update stock and sold counters for every ordered product.
            foreach ($data['items'] as $item) {
                Product::where('id', $item['product_id'])
                    ->decrement('stock', $item['quantity']);
                Product::where('id', $item['product_id'])
                    ->increment('sold_count', $item['quantity']);
            }

            return $order->load('items');
        });

        // Notify the buyer their order was received (database + broadcast + FCM).
        $request->user()->notify(new OrderStatusNotification(
            type: 'order_status',
            title: 'Order placed',
            message: "Your order {$order->order_number} has been placed successfully.",
            orderId: $order->id,
            status: 'Pending',
        ));

        return response()->json([
            'message' => 'Order placed successfully.',
            'order' => $order,
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/orders/{id}
     */
    public function show(Request $request, $id)
    {
        $order = $this->resolveOrder($request, $id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $order->load('items');

        return response()->json([
            'message' => 'success',
            'order' => $order,
        ]);
    }

    /**
     * GET /api/orders/{id}/tracking
     *
     * Returns the order snapshot (with items) plus its full status
     * timeline, newest first, for the Flutter tracking screen.
     * The order is resolved by its numeric id or order_number so
     * both identifiers used by the client work.
     */
    public function tracking(Request $request, $id)
    {
        $order = $this->resolveOrder($request, $id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $order->load('items');

        return response()->json([
            'message' => 'success',
            'order' => $order,
            'tracking' => $order->trackings()->latest()->get(),
        ]);
    }

    /**
     * POST /api/orders/{id}/reviews
     *
     * Stores one review per rated order line. Only completed
     * or delivered orders can be reviewed. Re-submitting
     * updates the existing review for that line.
     */
    public function reviews(Request $request, $id)
    {
        $order = $this->resolveOrder($request, $id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!in_array($order->status, ['Completed', 'Delivered'], true)) {
            return response()->json([
                'message' => "Only completed orders can be reviewed (current status: {$order->status}).",
            ], Response::HTTP_CONFLICT);
        }

        $data = $request->validate([
            'reviews' => ['required', 'array', 'min:1'],
            'reviews.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'reviews.*.rating' => ['required', 'integer', 'between:1,5'],
            'reviews.*.comment' => ['nullable', 'string', 'max:2000'],
            'reviews.*.image_paths' => ['nullable', 'array'],
            'reviews.*.image_paths.*' => ['nullable', 'string', 'max:500'],
        ]);

        // Keyed by id so each submitted line can be matched
        // against the order's own items.
        $items = $order->items->keyBy('id');

        $reviews = [];

        foreach ($data['reviews'] as $review) {
            $item = $items->get((int) $review['order_item_id']);

            if (!$item) {
                return response()->json([
                    'message' => 'Review references an item that is not part of this order.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $reviews[] = $item->reviews()->updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'product_id' => $item->product_id,
                    'rating' => $review['rating'],
                    'comment' => $review['comment'] ?? null,
                    'image_paths' => $review['image_paths'] ?? null,
                ],
            );
        }

        return response()->json([
            'message' => 'Review submitted successfully.',
            'reviews' => $reviews,
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT|PATCH /api/orders/{id}
     * Only the status can be updated (matches the Flutter Order.copyWith
     * usage where status is the mutable field).
     */
    public function update(Request $request, $id)
    {
        $order = $request->user()->orders()->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $order->update(['status' => $data['status']]);

        // Append the new status to the tracking timeline.
        $order->trackings()->create([
            'status' => $data['status'],
            'description' => $this->statusDescription($data['status']),
            'tracking_date' => now()->toDateString(),
            'tracking_time' => now(),
            'is_completed' => in_array($data['status'], ['Completed', 'Delivered'], true),
        ]);

        // Notify the buyer of the new status (database + broadcast + FCM).
        $request->user()->notify(new OrderStatusNotification(
            type: 'order_status',
            title: "Order {$data['status']}",
            message: "Your order {$order->order_number} is now {$data['status']}.",
            orderId: $order->id,
            status: $data['status'],
        ));

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => $order->fresh()->load('items'),
        ]);
    }

    /**
     * DELETE /api/orders/{id}
     * Only pending orders can be deleted (later stages are history).
     */
    public function destroy(Request $request, $id)
    {
        $order = $request->user()->orders()->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($order->status !== 'Pending') {
            return response()->json([
                'message' => "Only pending orders can be deleted (current status: {$order->status}).",
            ], Response::HTTP_CONFLICT);
        }

        // Restore stock and sold counters before removing the order
        // (skipped for items whose product no longer exists).
        foreach ($order->items as $item) {
            if ($item->product_id) {
                Product::where('id', $item->product_id)
                    ->increment('stock', $item->quantity);
                Product::where('id', $item->product_id)
                    ->decrement('sold_count', $item->quantity);
            }
        }

        $order->delete(); // Items are removed via cascade.

        return response()->json([
            'message' => 'Order deleted successfully.',
        ]);
    }

    /**
     * Resolve an order by its numeric id or order_number, scoped
     * to the authenticated user.
     */
    private function resolveOrder(Request $request, $id)
    {
        return $request->user()->orders()
            ->where(function ($query) use ($id) {
                $query->where('order_number', $id);
                // Only compare the bigint id column when the
                // identifier is numeric, otherwise PostgreSQL
                // rejects the cast (SQLSTATE 22P02).
                if (is_numeric($id)) {
                    $query->orWhere('id', (int) $id);
                }
            })
            ->first();
    }

    /**
     * Human-readable description recorded alongside a status change.
     */
    private function statusDescription(string $status): string
    {
        return match ($status) {
            'Pending' => 'Order is in Packing',
            'In Delivery' => 'Order In Transit',
            'Completed' => 'Order Completed',
            'Delivered' => 'Order Delivered',
            'Cancelled' => 'Order Cancelled',
            default => 'Status updated.',
        };
    }
}