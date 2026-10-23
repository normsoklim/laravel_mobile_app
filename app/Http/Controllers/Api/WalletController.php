<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Notifications\GeneralNotification;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * E-Wallet endpoints (Sanctum protected).
 *
 *   GET  /api/wallet                  → wallet balance
 *   GET  /api/wallet/transactions     → transaction history
 *   GET  /api/wallet/transactions/{id}
 *   POST /api/wallet/top-up           → development top-up
 *   POST /api/wallet/test-payment     → development payment
 *
 * The balance is only ever changed server-side by WalletService.
 */
class WalletController extends Controller
{
    public function __construct(private WalletService $walletService)
    {
    }

    /**
     * GET /api/wallet
     *
     * Returns the authenticated user's wallet, creating it on
     * first access with a zero balance.
     */
    public function balance(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet($request->user());

        return response()->json([
            'message' => 'success',
            'wallet' => [
                'id' => $wallet->id,
                'balance' => $wallet->balance,
                'currency' => $wallet->currency,
            ],
        ]);
    }

    /**
     * GET /api/wallet/transactions
     *
     * List the authenticated user's wallet transactions (newest
     * first). Optional query params: type (Orders|Top Up),
     * limit (1-100, default 50).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(WalletTransaction::TYPES)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $transactions = $request->user()
            ->walletTransactions()
            ->when(
                $data['type'] ?? null,
                fn ($q, $type) => $q->where('type', $type)
            )
            ->latest()
            ->limit((int) ($data['limit'] ?? 50))
            ->get();

        return response()->json([
            'message' => 'success',
            'transactions' => $transactions,
        ]);
    }

    /**
     * GET /api/wallet/transactions/{id}
     */
    public function show(Request $request, $id): JsonResponse
    {
        $transaction = $request->user()->walletTransactions()->find($id);

        if (! $transaction) {
            return response()->json([
                'message' => 'Transaction not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'success',
            'transaction' => $transaction,
        ]);
    }

    /**
     * POST /api/wallet/top-up
     *
     * DEVELOPMENT endpoint. Credits the wallet immediately so
     * the top-up flow can be tested end to end.
     *
     * Do not ship to production: production top-ups must be
     * verified by Bakong KHQR first (create a pending
     * transaction, customer pays, Laravel verifies, then
     * credit the wallet).
     */
    public function topUp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);

        try {
            $transaction = $this->walletService->credit(
                $request->user(),
                (float) $data['amount'],
                'Top Up',
                'Wallet top up',
            );

            // Automatic notification after the wallet is credited
            // (database + broadcast + FCM).
            $request->user()->notify(new GeneralNotification(
                type: 'wallet',
                title: 'Wallet topped up',
                message: "Your wallet has been credited with \${$data['amount']}.",
            ));

            return response()->json([
                'message' => 'Wallet topped up successfully.',
                'transaction' => $transaction,
            ], Response::HTTP_CREATED);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function testPayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $transaction = $this->walletService->debit(
                $request->user(),
                (float) $data['amount'],
                'payment',
                $data['description'] ?? 'Wallet payment',
            );

            // Automatic notification after the wallet is debited
            // (database + broadcast + FCM).
            $request->user()->notify(new GeneralNotification(
                type: 'wallet',
                title: 'Wallet payment successful',
                message: "A payment of \${$data['amount']} from your wallet was successful.",
            ));

            return response()->json([
                'message' => 'Wallet payment successful.',
                'transaction' => $transaction,
            ], Response::HTTP_CREATED);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * POST /api/wallet/pay
     *
     * Deducts the given amount from the authenticated user's wallet
     * for an order payment. Creates a wallet transaction of type "Orders".
     */
    public function pay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $transaction = $this->walletService->debit(
                $request->user(),
                (float) $data['amount'],
                'Orders',
                $data['description'] ?? 'Order payment',
                reference: null,
                orderId: $data['order_id'] ?? null,
            );

            // Automatic notification after the wallet pays for the order
            // (database + broadcast + FCM).
            $request->user()->notify(new GeneralNotification(
                type: 'wallet',
                title: 'Wallet payment successful',
                message: "A payment of \${$data['amount']} from your wallet was successful.",
                data: $data['order_id'] ?? null ? ['order_id' => (string) $data['order_id']] : [],
            ));

            return response()->json([
                'message' => 'Wallet payment successful.',
                'transaction' => $transaction,
            ], Response::HTTP_CREATED);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
