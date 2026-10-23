<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Validation rules shared by store() and update().
     * Pass `false` to get partial-update rules (all fields optional).
     */
    private function rules(bool $creating = true, ?int $ignoreId = null): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'type' => [
                $required,
                'string',
                'max:30',
                Rule::in(array_keys(Payment::TYPES)),
                $ignoreId
                    ? Rule::unique('payments', 'type')->ignore($ignoreId)
                    : Rule::unique('payments', 'type'),
            ],
            'label' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            // Material icon name used by the Flutter client,
            // e.g. "qr_code_2_outlined" (used as fallback).
            'icon' => ['nullable', 'string', 'max:255'],
            // Cloudinary URL (or a bundled asset path) of the icon that the
            // Flutter client renders, e.g.
            // "https://res.cloudinary.com/.../wallet.png".
            'icon_url' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * GET /api/payments
     * List payment methods (active only unless ?all=1). The payload
     * maps 1:1 to the Flutter PaymentMethod model.
     */
    public function index(Request $request)
    {
        $payments = Payment::query()
            ->when(
                ! $request->boolean('all'),
                fn ($q) => $q->where('is_active', true)
            )
            ->orderBy('id')
            ->get();

        return response()->json([
            'message' => 'success',
            'payments' => $payments,
        ]);
    }

    /**
     * POST /api/payments
     */
    public function store(Request $request)
    {
        $payment = Payment::create(
            $request->validate($this->rules())
        );

        return response()->json([
            'message' => 'Payment method created successfully.',
            'payment' => $payment,
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/payments/{id}
     */
    public function show($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment method not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'success',
            'payment' => $payment,
        ]);
    }

    /**
     * PUT|PATCH /api/payments/{id}
     * Partial updates are supported — only the sent fields are updated.
     */
    public function update(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment method not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = collect($request->validate(
            $this->rules(creating: false, ignoreId: $payment->id)
        ))->filter(fn ($value) => $value !== null)->all();

        $payment->update($data);

        return response()->json([
            'message' => 'Payment method updated successfully.',
            'payment' => $payment->fresh(),
        ]);
    }

    /**
     * DELETE /api/payments/{id}
     * Safe to delete: past orders keep their payment_method string.
     */
    public function destroy($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment method not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $payment->delete();

        return response()->json([
            'message' => 'Payment method deleted successfully.',
        ]);
    }

    /**
     * POST /api/payments/{id}/icon
     *
     * Uploads a PNG/SVG icon to Cloudinary and stores the resulting URL on the
     * payment method so the Flutter app can render the icon in real time.
     */
    public function uploadIcon(Request $request, $id, CloudinaryService $cloudinary)
    {
        $payment = Payment::find($id);

        if (! $payment) {
            return response()->json([
                'message' => 'Payment method not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $request->validate([
            'icon' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        $uploadedFile = $request->file('icon');

        // Upload to Cloudinary and store the secure URL on the record.
        // Pass the real temp file path (string) — the raw cloudinary_php SDK
        // does not accept a Laravel UploadedFile object directly.
        $iconUrl = $cloudinary->uploadAndGetUrl(
            $uploadedFile->getRealPath(),
            [],
            'tinh_shop/payments'
        );

        $payment->update([
            'icon_url' => $iconUrl,
        ]);

        return response()->json([
            'message' => 'Icon uploaded successfully.',
            'payment' => $payment->fresh(),
        ]);
    }
}