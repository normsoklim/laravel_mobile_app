<?php



use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;

$customer = User::where('role', 'customer')->first();
$driver = User::where('role', 'delivery')->first();

if (! $customer || ! $driver) {
    echo "SKIP: no customer/driver user in the database\n";

    return;
}

$order = Order::create([
    'order_number' => 'GPS-TEST-' . strtoupper(substr(md5((string) microtime(true)), 0, 6)),
    'user_id' => $customer->id,
    'subtotal' => 10,
    'delivery_fee' => 5,
    'discount' => 0,
    'total' => 15,
    'address' => 'Street 271, Phnom Penh',
    'latitude' => 11.5564,
    'longitude' => 104.9282,
    'payment_method' => 'E-Wallet',
    'status' => 'Pending',
]);

$order->refresh();

echo 'order id            : ' . $order->id . PHP_EOL;
echo 'latitude cast       : ' . var_export($order->latitude, true) . PHP_EOL;
echo 'longitude cast      : ' . var_export($order->longitude, true) . PHP_EOL;
echo 'hasCoordinates()    : ' . var_export($order->hasCoordinates(), true) . PHP_EOL;

$delivery = Delivery::create([
    'order_id' => $order->id,
    'delivery_user_id' => $driver->id,
    'status' => 'assigned',
    'assigned_at' => now(),
]);

// The same payload the controller builds.
$destination = [
    'address' => $delivery->order?->address,
    'latitude' => $delivery->order?->latitude !== null ? (float) $delivery->order->latitude : null,
    'longitude' => $delivery->order?->longitude !== null ? (float) $delivery->order->longitude : null,
];

echo 'destination payload : ' . json_encode($destination) . PHP_EOL;

// The driver sends its live position.
$delivery->updateLocation(11.5610, 104.9330, 8.5);
$delivery->refresh();

echo 'current_latitude    : ' . var_export((float) $delivery->current_latitude, true) . PHP_EOL;
echo 'current_longitude   : ' . var_export((float) $delivery->current_longitude, true) . PHP_EOL;
echo 'location rows       : ' . $delivery->locations()->count() . PHP_EOL;

// Clean up.
$delivery->delete();
$order->delete();

echo 'cleanup             : done' . PHP_EOL;
