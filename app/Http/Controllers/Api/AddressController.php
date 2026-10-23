<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\GoogleMapsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AddressController extends Controller
{
    /**
     * GET /api/addresses
     * List the authenticated user's addresses (default first, newest first).
     */
    public function index(Request $request)
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'message' => 'success',
            'addresses' => $addresses,
        ]);
    }

    /**
     * POST /api/addresses
     * Store a new address for the authenticated user.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            // Street and city are optional — they may be null
            // even when no latitude/longitude was supplied
            // (e.g. a manually entered address or a bare pin).
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data = $this->applyReverseGeocoding($data);

        $address = $this->createAddress($request, $data);

        return response()->json([
            'message' => 'Address created.',
            'address' => $address,
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/addresses/{id}
     */
    public function show(Request $request, $id)
    {
        $address = $this->findUserAddress($request, $id);

        if (! $address) {
            return response()->json([
                'message' => 'Address not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'success',
            'address' => $address,
        ]);
    }

    /**
     * PUT|PATCH /api/addresses/{id}
     * Update an address (fields are optional; only sent fields are changed).
     */
    public function update(Request $request, $id)
    {
        $address = $this->findUserAddress($request, $id);

        if (! $address) {
            return response()->json([
                'message' => 'Address not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:50'],
            'recipient_name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            // Street and city are optional — they may be null
            // even when no latitude/longitude was supplied.
            'street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data = $this->applyReverseGeocoding($data);

        if ($data['is_default'] ?? false) {
            $this->clearDefaultAddress($request, $address->id);
        }

        $address->update($data);

        return response()->json([
            'message' => 'Address updated.',
            'address' => $address->fresh(),
        ]);
    }

    /**
     * PATCH /api/addresses/{id}/set-default
     * Mark the address as the user's default (clears the previous default).
     */
    public function setDefault(Request $request, $id)
    {
        $address = $this->findUserAddress($request, $id);

        if (! $address) {
            return response()->json([
                'message' => 'Address not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->clearDefaultAddress($request, $address->id);
        $address->update(['is_default' => true]);

        return response()->json([
            'message' => 'Default address updated.',
            'address' => $address->fresh(),
        ]);
    }

    /**
     * DELETE /api/addresses/{id}
     * Delete an address; if it was the default, the most recent
     * remaining address is promoted.
     */
    public function destroy(Request $request, $id)
    {
        $address = $this->findUserAddress($request, $id);

        if (! $address) {
            return response()->json([
                'message' => 'Address not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $request->user()
                ->addresses()
                ->orderByDesc('created_at')
                ->first()
                ?->update(['is_default' => true]);
        }

        return response()->json([
            'message' => 'Address deleted.',
        ]);
    }

    /**
     * Create an address; the user's first address becomes the default.
     */
    private function createAddress(Request $request, array $data): Address
    {
        $isFirstAddress = ! $request->user()->addresses()->exists();
        $isDefault = $isFirstAddress || ($data['is_default'] ?? false);

        if ($isDefault) {
            $this->clearDefaultAddress($request);
        }

        return $request->user()->addresses()->create(
            $data + ['is_default' => $isDefault]
        );
    }

    /**
     * Clear the default flag on the user's other addresses.
     */
    private function clearDefaultAddress(Request $request, ?int $exceptId = null): void
    {
        $query = $request->user()->addresses()
            ->where('is_default', true)
            ->where('id', '!=', $exceptId);

        $query->update(['is_default' => false]);
    }

    /**
     * When the client only sent a map pin, ask Google to
     * fill in the street/city the user didn't type.
     */
    private function applyReverseGeocoding(array $data): array
    {
        $lat = $data['latitude'] ?? null;
        $lng = $data['longitude'] ?? null;

        if ($lat === null || $lng === null) {
            return $data;
        }

        $geo = app(GoogleMapsService::class)->reverseGeocode(
            (float) $lat,
            (float) $lng,
        );

        if (empty($data['street']) && $geo['address']) {
            $data['street'] = $geo['address'];
        }

        if (empty($data['city']) && $geo['city']) {
            $data['city'] = $geo['city'];
        }

        return $data;
    }

    /**
     * Find an address owned by the authenticated user.
     */
    private function findUserAddress(Request $request, $id): ?Address
    {
        return $request->user()->addresses()->where('id', $id)->first();
    }
}
