<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DeliveryStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Delivery management API.
 *
 * Roles:
 *   - admin/staff: assign delivery persons, view all deliveries
 *   - delivery: view own deliveries, update status, send GPS
 *   - customer: view tracking for their own orders
 */
class DeliveryController extends Controller
{
    /**
     * GET /api/deliveries/available
     * Admin/staff: list paid/confirmed orders that have no delivery yet.
     */
    public function available(Request $request)
    {
        $this->authorizeRole($request->user(), ['admin', 'staff']);

        $orders = Order::where('status', 'Pending')
            ->whereDoesntHave('delivery')
            ->with('items')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'success',
            'orders' => $orders,
        ]);
    }

    /**
     * GET /api/deliveries/drivers
     * Admin/staff: list the users that can be assigned to a delivery.
     */
    public function drivers(Request $request)
    {
        $this->authorizeRole($request->user(), ['admin', 'staff']);

        $drivers = User::whereIn('role', ['delivery', 'admin', 'staff'])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'phone', 'email', 'avatar', 'role']);

        return response()->json([
            'message' => 'success',
            'drivers' => $drivers,
        ]);
    }

    /**
     * POST /api/deliveries/assign
     * Admin/staff: assign a delivery person to an order.
     *
     * Body: { order_id, delivery_user_id }
     */
    public function assign(Request $request)
    {
        $this->authorizeRole($request->user(), ['admin', 'staff']);

        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'delivery_user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if (!$user || !in_array($user->role, ['delivery', 'admin', 'staff'], true)) {
                        $fail('The selected user is not a delivery person.');
                    }
                },
            ],
        ]);

        $order = Order::find($data['order_id']);

        if ($order->delivery) {
            return response()->json([
                'message' => 'This order already has a delivery assigned.',
            ], Response::HTTP_CONFLICT);
        }

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'delivery_user_id' => $data['delivery_user_id'],
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $delivery->load(['deliveryUser', 'order']);

        // Notify the delivery person they have a new assignment.
        $delivery->deliveryUser->notify(new DeliveryStatusNotification(
            type: 'delivery_assigned',
            title: 'New delivery assigned',
            message: "Order #{$order->order_number} has been assigned to you.",
            orderId: $order->id,
            deliveryId: $delivery->id,
            status: 'assigned',
        ));

        // Also notify the customer.
        $order->user->notify(new DeliveryStatusNotification(
            type: 'delivery_assigned',
            title: 'Delivery person assigned',
            message: "A delivery person has been assigned to your order #{$order->order_number}.",
            orderId: $order->id,
            deliveryId: $delivery->id,
            status: 'assigned',
        ));

        return response()->json([
            'message' => 'Delivery assigned successfully.',
            'delivery' => $delivery,
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/deliveries/my
     * Delivery person: list their own deliveries.
     */
    public function myDeliveries(Request $request)
    {
        $this->authorizeRole($request->user(), ['delivery', 'admin', 'staff']);

        $deliveries = $request->user()->deliveries()
            ->with(['order', 'order.items'])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'success',
            'deliveries' => $deliveries,
        ]);
    }

    /**
     * GET /api/deliveries/{id}
     * View a single delivery (driver assigned to it, admin, or the order's customer).
     */
    public function show(Request $request, $id)
    {
        $delivery = Delivery::with(['order', 'order.items', 'deliveryUser'])->find($id);

        if (!$delivery) {
            return response()->json([
                'message' => 'Delivery not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $request->user();

        // Authorization: driver, admin/staff, or the order's customer.
        if ($user->role === 'delivery' && $delivery->delivery_user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not assigned to this delivery.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->role === 'customer' && $delivery->order->user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not the customer for this order.',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'success',
            'delivery' => $delivery,
            'destination' => $this->destination($delivery),
        ]);
    }

    /**
     * PATCH /api/deliveries/{id}/status
     * Delivery person: transition the delivery status.
     *
     * Body: { status }
     */
    public function updateStatus(Request $request, $id)
    {
        $this->authorizeRole($request->user(), ['delivery', 'admin', 'staff']);

        $delivery = Delivery::find($id);

        if (!$delivery) {
            return response()->json([
                'message' => 'Delivery not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Only the assigned driver or admin/staff can change status.
        if ($request->user()->role === 'delivery'
            && $delivery->delivery_user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not assigned to this delivery.',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in([
                'assigned', 'accepted', 'picked_up', 'on_the_way',
                'delivered', 'cancelled', 'failed',
            ])],
        ]);

        $newStatus = $data['status'];

        if ($request->user()->role === 'delivery' && !$delivery->canTransitionTo($newStatus)) {
            return response()->json([
                'message' => "Cannot transition from {$delivery->status} to {$newStatus}.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $delivery->transitionTo($newStatus);
        $delivery->refresh();

        // Broadcast the status change to the order's customer and the driver.
        $this->broadcastDeliveryUpdate($delivery, 'status_changed');

        // Notify the customer of the status change.
        $delivery->order->user->notify(new DeliveryStatusNotification(
            type: 'delivery_status',
            title: "Delivery {$newStatus}",
            message: "Your order #{$delivery->order->order_number} is now {$newStatus}.",
            orderId: $delivery->order->id,
            deliveryId: $delivery->id,
            status: $newStatus,
        ));

        return response()->json([
            'message' => 'Delivery status updated.',
            'delivery' => $delivery,
        ]);
    }

    /**
     * POST /api/deliveries/{id}/location
     * Delivery person: send live GPS coordinates.
     *
     * Body: { latitude, longitude, accuracy? }
     */
    public function updateLocation(Request $request, $id)
    {
        $this->authorizeRole($request->user(), ['delivery', 'admin', 'staff']);

        $delivery = Delivery::find($id);

        if (!$delivery) {
            return response()->json([
                'message' => 'Delivery not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Only the assigned driver can update location (security: never trust client user_id).
        if ($request->user()->role === 'delivery'
            && $delivery->delivery_user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not assigned to this delivery.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Don't accept location updates for terminal deliveries.
        if ($delivery->isTerminal()) {
            return response()->json([
                'message' => 'Cannot update location for a completed or cancelled delivery.',
            ], Response::HTTP_CONFLICT);
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        $delivery->updateLocation(
            (float) $data['latitude'],
            (float) $data['longitude'],
            $data['accuracy'] ? (float) $data['accuracy'] : null
        );

        // Broadcast live location to the customer watching this delivery.
        $this->broadcastLocationUpdate($delivery);

        return response()->json([
            'message' => 'Location updated.',
            'delivery' => $delivery->only([
                'id', 'current_latitude', 'current_longitude', 'location_updated_at',
            ]),
        ]);
    }

    /**
     * GET /api/deliveries/{id}/tracking
     * Customer: get live tracking info for their order's delivery.
     * Returns the delivery + driver's latest location.
     */
    public function tracking(Request $request, $id)
    {
        $delivery = Delivery::where('order_id', $id)
            ->with(['deliveryUser', 'order', 'latestLocation'])
            ->first();

        if (!$delivery) {
            return response()->json([
                'message' => 'No delivery found for this order.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Only the order's customer, the driver, or admin/staff can view.
        $user = $request->user();
        if ($user->role === 'customer' && $delivery->order->user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not the customer for this order.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->role === 'delivery' && $delivery->delivery_user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not assigned to this delivery.',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'success',
            'delivery' => $delivery,
            'driver' => $delivery->deliveryUser ? [
                'id' => $delivery->deliveryUser->id,
                'name' => $delivery->deliveryUser->full_name,
                'phone' => $delivery->deliveryUser->phone,
            ] : null,
            'location' => $delivery->current_latitude && $delivery->current_longitude ? [
                'latitude' => (float) $delivery->current_latitude,
                'longitude' => (float) $delivery->current_longitude,
                'updated_at' => $delivery->location_updated_at,
            ] : null,
            // The map pin the customer picked — the drop-off point.
            'destination' => $this->destination($delivery),
        ]);
    }

    /**
     * GET /api/deliveries
     * Admin/staff: list all deliveries with optional status filter.
     */
    public function index(Request $request)
    {
        $this->authorizeRole($request->user(), ['admin', 'staff']);

        $query = Delivery::with(['order', 'deliveryUser']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $paginator = $query->latest()->paginate(15);

        return response()->json([
            'message' => 'success',
            // A plain list so the Flutter client can map it directly.
            'deliveries' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * The delivery destination of a delivery.
     *
     * Built from the coordinates snapshotted on the order when the
     * customer placed it, so the driver and the live-tracking map can
     * show where the parcel has to go. The coordinates are null when
     * the customer typed the address manually.
     *
     * @return array{address: string|null, latitude: float|null, longitude: float|null}
     */
    private function destination(Delivery $delivery): array
    {
        $order = $delivery->order;

        return [
            'address' => $order?->address,
            'latitude' => $order?->latitude !== null ? (float) $order->latitude : null,
            'longitude' => $order?->longitude !== null ? (float) $order->longitude : null,
        ];
    }

    /**
     * Ensure the authenticated user has one of the allowed roles.
     */
    private function authorizeRole($user, array $allowed): void
    {
        if (!$user || !in_array($user->role, $allowed, true)) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to perform this action.');
        }
    }

    /**
     * Broadcast a delivery update on a private channel scoped to the order.
     * Channel: private-delivery.{orderId}
     */
    private function broadcastDeliveryUpdate(Delivery $delivery, string $eventType): void
    {
        try {
            Broadcast::channel("private-delivery.{$delivery->order_id}", function ($user) use ($delivery) {
                return (int) $user->getAuthIdentifier() === (int) $delivery->order->user_id
                    || (int) $user->getAuthIdentifier() === (int) $delivery->delivery_user_id
                    || in_array($user->role, ['admin', 'staff'], true);
            });

            broadcast(new \App\Events\DeliveryUpdated(
                $delivery,
                $eventType
            ))->toOthers();

            Log::info("Broadcast delivery update: {$eventType} for delivery {$delivery->id}");
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast delivery update: '.$e->getMessage());
        }
    }

    /**
     * Broadcast a live location update on the delivery's private channel.
     */
    private function broadcastLocationUpdate(Delivery $delivery): void
    {
        try {
            broadcast(new \App\Events\LocationUpdated(
                $delivery,
                (float) $delivery->current_latitude,
                (float) $delivery->current_longitude,
                $delivery->location_updated_at
            ))->toOthers();

            Log::info("Broadcast location update for delivery {$delivery->id}");
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast location update: '.$e->getMessage());
        }
    }
}
