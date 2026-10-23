<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CheckoutSummary;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
   
    public function summary(Request $request)
    {
        $data = $request->validate([
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);
        $cart->load('items.product');

        $summary = CheckoutSummary::fromCart(
            $cart,
            (float) ($data['delivery_fee'] ?? 0),
            (float) ($data['discount'] ?? 0),
        );

        return response()->json([
            'message' => 'success',
            'checkout_summary' => $summary,
        ]);
    }
}
