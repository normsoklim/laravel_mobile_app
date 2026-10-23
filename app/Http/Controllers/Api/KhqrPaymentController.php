<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Notifications\OrderStatusNotification;
use App\Services\Payment\BakongException;
use App\Services\Payment\BakongService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Bakong KHQR payment flow:
 *
 *   1. createKhqr  — POST /payments/khqr            → generate QR for an order
 *   2. status      — GET  /payments/khqr/{id}/status → poll payment status
 *   3. check       — POST /payments/khqr/{id}/check  → verify with Bakong
 *
 * The backend is the source of truth: the order is only marked paid after
 * Bakong confirms the transaction via check-transaction-by-md5.
 */
class KhqrPaymentController extends Controller
{
    public function __construct(private BakongService $bakong)
    {
    }

    public function createKhqr(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            // Customer may pay in USD or Cambodian Riel (KHR).
            'currency' => ['nullable', 'string', 'in:USD,KHR,usd,khr'],
        ]);

        /** @var Order $order */
        $order = Order::findOrFail($data['order_id']);
        $currency = $this->bakong->normalizeCurrency($data['currency'] ?? null);

        // The order must belong to the authenticated user.
        if ($order->user_id !== $request->user()->id) {
            abort(403, 'This order does not belong to you.');
        }

        // Only pending orders can be paid (anything later means it is
        // already confirmed / paid / cancelled).
        if ($order->status !== 'Pending') {
            return response()->json([
                'message' => "Order is already {$order->status} and cannot be paid again.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Reuse an active pending payment so refreshing the screen does
        // not generate a new QR every time — but only when the currency
        // matches. Switching USD ↔ Riel cancels the old QR and issues a
        // fresh one in the requested currency.
        $existing = $order->paymentTransactions()
            ->where('status', PaymentTransaction::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($existing && strtoupper((string) $existing->currency) === $currency) {
            return response()->json([
                'message' => 'KHQR payment already created.',
                'payment' => $this->present($existing),
            ]);
        }

        if ($existing) {
            $existing->update(['status' => PaymentTransaction::STATUS_CANCELLED]);
        }

        try {
            $khqr = $this->bakong->generateKhqr(
                amount: (float) $order->total,
                billNumber: $order->order_number,
                currency: $currency,
            );
        } catch (BakongException $e) {
            Log::error('KHQR generation failed for order '.$order->id, [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        $payment = $order->paymentTransactions()->create([
            'method' => 'bakong_khqr',
            'status' => PaymentTransaction::STATUS_PENDING,
            // KHR payments store the converted Riel amount; USD keeps the
            // order total as-is.
            'amount' => $khqr['amount'],
            'currency' => $khqr['currency'],
            'md5' => $khqr['md5'],
            'qr' => $khqr['qr'],
            // Same expiry that is embedded in the QR (tag 99).
            'expires_at' => $khqr['expires_at'],
            'metadata' => [
                'bill_number' => $order->order_number,
                'order_total_usd' => (float) $order->total,
            ],
        ]);

        return response()->json([
            'message' => 'KHQR payment created.',
            'payment' => $this->present($payment),
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /payments/khqr/{id}/status
     *
     * Lightweight polling endpoint. Marks the payment expired when its
     * validity window has passed.
     */
    public function status(Request $request, int $id): JsonResponse
    {
        $payment = PaymentTransaction::with('order')->find($id);

        if (! $payment) {
            return response()->json([
                'message' => 'Payment not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($payment->order->user_id !== $request->user()->id) {
            abort(403, 'This payment does not belong to you.');
        }

        if ($payment->isExpired()) {
            $payment->update(['status' => PaymentTransaction::STATUS_EXPIRED]);
        }

        return response()->json([
            'message' => 'success',
            'payment' => $this->present($payment->fresh()),
        ]);
    }

    /**
     * POST /payments/khqr/{id}/check
     *
     * Asks Bakong whether the transaction behind the payment's MD5 has
     * been paid. When Bakong confirms, the payment is marked paid and the
     * order moves from Pending to In Delivery (confirmed).
     */
    public function check(Request $request, int $id): JsonResponse
    {
        $payment = PaymentTransaction::with('order')->find($id);

        if (! $payment) {
            return response()->json([
                'message' => 'Payment not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($payment->order->user_id !== $request->user()->id) {
            abort(403, 'This payment does not belong to you.');
        }

        // Already settled — nothing to verify.
        if ($payment->status === PaymentTransaction::STATUS_PAID) {
            return response()->json([
                'message' => 'Payment already confirmed.',
                'payment' => $this->present($payment),
            ]);
        }

        if ($payment->isExpired()) {
            $payment->update(['status' => PaymentTransaction::STATUS_EXPIRED]);

            // Notify the buyer the payment window closed (database + broadcast + FCM).
            $payment->order->user?->notify(new OrderStatusNotification(
                type: 'payment',
                title: 'Payment expired',
                message: "The payment for order {$payment->order->order_number} has expired. Please create a new payment.",
                orderId: $payment->order->id,
                status: $payment->order->status,
            ));

            return response()->json([
                'message' => 'This QR code has expired. Please create a new payment.',
                'payment' => $this->present($payment->fresh()),
            ], Response::HTTP_GONE);
        }

        if (! $payment->md5) {
            return response()->json([
                'message' => 'Payment has no Bakong reference to verify.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->bakong->checkTransactionByMd5($payment->md5);
        } catch (BakongException $e) {
            Log::error('KHQR verification failed for payment '.$payment->id, [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        if ($result['paid']) {
            $payment->update([
                'status' => PaymentTransaction::STATUS_PAID,
                'paid_at' => now(),
                'transaction_id' => $result['transaction_id'],
                'metadata' => array_merge($payment->metadata ?? [], [
                    'bakong_check_response' => $result['raw'],
                ]),
            ]);

            // Confirm the order (Pending → In Delivery).
            $payment->order->update(['status' => 'In Delivery']);

            // Record the payment confirmation in the tracking timeline so
            // the customer sees the milestone, not just a status jump.
            $payment->order->trackings()->create([
                'status' => 'In Delivery',
                'description' => 'Payment confirmed',
                'location' => $payment->order->address,
                'tracking_date' => now()->toDateString(),
                'tracking_time' => now(),
                'is_completed' => false,
            ]);

            // Notify the buyer their payment was verified by Bakong
            // (database + broadcast + FCM). This is the "payment
            // successful" event — created only after Laravel itself
            // confirmed the transaction, never when the order is placed.
            $payment->order->user?->notify(new OrderStatusNotification(
                type: 'payment',
                title: 'Payment successful',
                message: "Payment for order {$payment->order->order_number} has been confirmed.",
                orderId: $payment->order->id,
                status: 'In Delivery',
            ));
        } else {
            // Keep the raw response for debugging but stay pending.
            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'last_check_response' => $result['raw'],
                    'last_checked_at' => now()->toIso8601String(),
                ]),
            ]);
        }

        return response()->json([
            'message' => $result['paid'] ? 'Payment confirmed.' : 'Payment not received yet.',
            'payment' => $this->present($payment->fresh()),
        ]);
    }

    /**
     * JSON payload consumed by the Flutter client. The QR string is
     * rendered locally with a QR painter — no Bakong data leaks beyond
     * what the customer needs to pay.
     */
    private function present(PaymentTransaction $payment): array
    {
        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'method' => $payment->method,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'qr' => $payment->qr,
            'md5' => $payment->md5,
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }
}