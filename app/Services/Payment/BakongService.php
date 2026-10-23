<?php

namespace App\Services\Payment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;

/**
 * Bakong KHQR integration.
 *
 * - KHQR strings are generated LOCALLY with the official KHQR SDK
 *   (fidele007/bakong-khqr-php, EMVCo format + CRC16). The Bakong Open API
 *   has no "generate QR" endpoint — it only issues deeplinks for existing
 *   QR strings.
 * - Payment verification goes through the Bakong Open API
 *   (POST /v1/check_transaction_by_md5) using the MD5 of the generated QR.
 *
 * All Bakong communication lives here so controllers never talk to the
 * gateway directly and the token never leaves the server.
 */
class BakongService
{
    protected ?string $token;

    protected bool $isTest;

    public function __construct()
    {
        $this->token = config('bakong.token');
        $this->isTest = (bool) config('bakong.test_mode', false);
    }

    /**
     * Generate a dynamic KHQR for one order.
     *
     * The order total is stored in USD; when the customer chooses to pay
     * in Riel the amount is converted with the configured USD→KHR rate
     * and rounded to a whole-Riel value (KHQR requires integer KHR amounts).
     *
     * @return array{qr: string, md5: string, expires_at: Carbon, currency: string, amount: float}
     *
     * @throws BakongException when the merchant is not configured or the
     *                         QR could not be built.
     */
    public function generateKhqr(
        float $amount,
        string $billNumber,
        ?string $currency = null,
    ): array {
        $accountId = config('bakong.account_id');

        if (! $accountId) {
            throw new BakongException(
                'Bakong account is not configured. Set BAKONG_ACCOUNT_ID in .env.'
            );
        }

        // Resolve the requested currency and convert the USD order total
        // when the customer pays in Riel.
        $currency = $this->normalizeCurrency($currency);
        $chargedAmount = $currency === 'KHR'
            ? $this->convertUsdToKhr($amount)
            : round($amount, 2);

        // Dynamic KHQRs must carry an expiration timestamp (tag 99, ms).
        $expiresAt = now()->addMinutes((int) config('bakong.qr_expiry_minutes', 15));

        $info = new IndividualInfo(
            bakongAccountID: $accountId,
            merchantName: (string) config('bakong.merchant_name'),
            merchantCity: (string) config('bakong.merchant_city'),
            currency: $this->currencyConstant($currency),
            amount: $chargedAmount,
            billNumber: $billNumber,
            expirationTimestamp: (string) ($expiresAt->getTimestampMs()),
        );

        $response = BakongKHQR::generateIndividual($info);

        $qr = $response->data['qr'] ?? null;
        $md5 = $response->data['md5'] ?? null;

        if (! $qr || ! $md5) {
            Log::error('KHQR generation returned an unexpected payload.', [
                'status' => $response->status,
            ]);

            throw new BakongException(
                'Could not generate a valid KHQR. Please try again.'
            );
        }

        return [
            'qr' => $qr,
            'md5' => $md5,
            'expires_at' => $expiresAt,
            'currency' => $currency,
            'amount' => (float) $chargedAmount,
        ];
    }

    /**
     * Ask Bakong whether the transaction behind a generated KHQR (identified
     * by its MD5) has been paid.
     *
     * "Transaction not found" is treated as "not paid yet" — that is the
     * normal answer while the customer has not scanned the QR.
     *
     * @return array{paid: bool, amount: ?float, currency: ?string, transaction_id: ?string, raw: array}
     *
     * @throws BakongException on transport / auth / unexpected API errors.
     */
    public function checkTransactionByMd5(string $md5): array
    {
        if (! $this->token) {
            throw new BakongException(
                'Bakong API token is not configured. Set BAKONG_API_TOKEN in .env.'
            );
        }

        try {
            $response = (new BakongKHQR($this->token))
                ->checkTransactionByMD5($md5, $this->isTest);
        } catch (\Throwable $e) {
            Log::error('Bakong check-transaction failed.', [
                'error' => $e->getMessage(),
            ]);

            throw new BakongException(
                'Could not reach the Bakong service. Please try again.'
            );
        }

        $responseCode = $response['responseCode'] ?? null;

        // responseCode 0 = transaction found; data.paid tells the state.
        if ($responseCode === 0) {
            $data = $response['data'] ?? [];

            return [
                'paid' => (bool) ($data['paid'] ?? false),
                'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
                'currency' => $data['currency'] ?? null,
                // Bakong returns the transaction hash; use it as our id.
                'transaction_id' => $data['hash'] ?? null,
                'raw' => $response,
            ];
        }

        // Not paid yet — Bakong reports the transaction as not found
        // ("Transaction not found" / "Transaction could not be found.").
        $message = (string) ($response['responseMessage'] ?? 'Bakong request failed.');

        if (preg_match('/not\s+(be\s+)?found/i', $message)) {
            return [
                'paid' => false,
                'amount' => null,
                'currency' => null,
                'transaction_id' => null,
                'raw' => $response,
            ];
        }

        Log::error('Bakong check-transaction returned an error response.', [
            'body' => $response,
        ]);

        throw new BakongException($message);
    }

    /**
     * Normalize a requested currency to 'USD' or 'KHR' (anything else
     * falls back to USD).
     */
    public function normalizeCurrency(?string $currency): string
    {
        return strtoupper(trim((string) $currency)) === 'KHR' ? 'KHR' : 'USD';
    }

    /**
     * Convert a USD amount to Cambodian Riel using the configured rate.
     * KHQR requires whole-Riel amounts, so the result is rounded.
     */
    public function convertUsdToKhr(float $usdAmount): int
    {
        $rate = (float) config('bakong.usd_khr_rate', 4100);

        if ($rate <= 0) {
            $rate = 4100;
        }

        return (int) round($usdAmount * $rate);
    }

    /**
     * Map a normalized currency ('USD' | 'KHR') to the KHQR constant.
     */
    private function currencyConstant(string $currency): int
    {
        return $currency === 'KHR'
            ? KHQRData::CURRENCY_KHR
            : KHQRData::CURRENCY_USD;
    }
}