<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpecialOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SpecialOfferController extends Controller
{
    private function rules(bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Percentage off, 0–100.
            'discount_percentage' => [$required, 'numeric', 'min:0', 'max:100'],
            // Image URL or asset path (e.g. Cloudinary/Firebase) can be long.
            'image' => ['nullable', 'string', 'max:2048'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function validatedData(Request $request, bool $creating = true): array
    {
        return collect($request->validate($this->rules($creating)))
            ->filter(fn ($value) => $value !== null)
            ->all();
    }

    /**
     * GET /api/special-offers
     * Public list of currently valid offers (active + within their
     * validity window), newest first. Powers the Special Offers screen.
     */
    public function index()
    {
        $offers = SpecialOffer::active()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'message' => 'success',
            'special_offers' => $offers,
        ]);
    }

    /**
     * POST /api/special-offers
     */
    public function store(Request $request)
    {
        $offer = SpecialOffer::create(
            $this->validatedData($request)
        );

        return response()->json([
            'message' => 'Special offer created successfully.',
            'special_offer' => $offer,
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/special-offers/{id}
     */
    public function show($id)
    {
        $offer = SpecialOffer::find($id);

        if (!$offer) {
            return response()->json([
                'message' => 'Special offer not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'success',
            'special_offer' => $offer,
        ]);
    }

    /**
     * PUT|PATCH /api/special-offers/{id}
     * Partial updates are supported — only the sent fields are updated.
     */
    public function update(Request $request, $id)
    {
        $offer = SpecialOffer::find($id);

        if (!$offer) {
            return response()->json([
                'message' => 'Special offer not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $offer->update(
            $this->validatedData($request, creating: false)
        );

        return response()->json([
            'message' => 'Special offer updated successfully.',
            'special_offer' => $offer->fresh(),
        ]);
    }

    /**
     * DELETE /api/special-offers/{id}
     */
    public function destroy($id)
    {
        $offer = SpecialOffer::find($id);

        if (!$offer) {
            return response()->json([
                'message' => 'Special offer not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $offer->delete();

        return response()->json([
            'message' => 'Special offer deleted successfully.',
        ]);
    }
}
