<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CountryController extends Controller
{
   
    public function index(Request $request)
    {
        $countries = Country::query()
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'success',
            'countries' => $countries,
        ]);
    }

    /**
     * GET /api/countries/{id}
     */
    public function show($id)
    {
        $country = Country::active()->find($id);

        if (!$country) {
            return response()->json([
                'message' => 'Country not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'success',
            'country' => $country,
        ]);
    }
}
