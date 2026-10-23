<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reverse geocoding for the coordinates the user drops on the map.
 *
 * The customer picks a pin in the Flutter address screen (OpenStreetMap
 * tiles, no API key needed). This service turns that pin into a
 * human-readable street and city so the address book stays searchable
 * even when the user only picked a pin.
 *
 * Two providers are used:
 *
 *   1. Google Maps Geocoding API — used first when a server key is
 *      configured (see `config/services.php`).
 *   2. Nominatim (OpenStreetMap) — free, no key required, and the very
 *      same service the Flutter map uses for its place search. This is
 *      the fallback so the feature works out of the box.
 */
class GoogleMapsService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.google_maps.key');
    }

    /**
     * Reverse geocode a coordinate pair.
     *
     * @return array{address: string|null, city: string|null, lat: float, lng: float}
     */
    public function reverseGeocode(float $lat, float $lng): array
    {
        $result = [
            'address' => null,
            'city' => null,
            'lat' => $lat,
            'lng' => $lng,
        ];

        // Without a server key there is nothing to call on Google —
        // fall back to the keyless provider.
        if ($this->apiKey === '') {
            return $this->merge($result, $this->reverseGeocodeWithNominatim($lat, $lng));
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "$lat,$lng",
                'key' => $this->apiKey,
            ]);

            if (! $response->ok()) {
                return $this->merge($result, $this->reverseGeocodeWithNominatim($lat, $lng));
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) {
                return $this->merge($result, $this->reverseGeocodeWithNominatim($lat, $lng));
            }

            $first = $data['results'][0];
            $result['address'] = $first['formatted_address'] ?? null;

            // Prefer the locality, fall back to the administrative area.
            foreach ($first['address_components'] ?? [] as $component) {
                $types = $component['types'] ?? [];
                if (in_array('locality', $types, true)
                    || in_array('administrative_area_level_1', $types, true)) {
                    $result['city'] = $component['long_name'] ?? null;
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Google Maps reverse geocoding failed: '.$e->getMessage());

            // Google is down or unreachable — try the keyless provider.
            return $this->merge($result, $this->reverseGeocodeWithNominatim($lat, $lng));
        }

        return $result;
    }

    /**
     * Reverse geocode a coordinate pair with Nominatim (OpenStreetMap).
     *
     * No API key is required, which keeps the map-based address picker
     * working on a fresh install. Nominatim's usage policy asks for an
     * identifying User-Agent, so one is always sent.
     *
     * @return array{address: string|null, city: string|null}
     */
    protected function reverseGeocodeWithNominatim(float $lat, float $lng): array
    {
        $empty = ['address' => null, 'city' => null];

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'tinh_shop/1.0 (delivery-address geocoding)',
                'Accept-Language' => 'en',
            ])->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'json',
                'zoom' => 18,
                'addressdetails' => 1,
            ]);

            if (! $response->ok()) {
                return $empty;
            }

            $data = $response->json();

            $address = $data['display_name'] ?? null;

            $city = null;
            foreach ($data['address'] ?? [] as $key => $value) {
                if (in_array($key, ['city', 'town', 'village', 'municipality', 'suburb'], true)) {
                    $city = is_string($value) ? $value : null;
                    break;
                }
            }

            return [
                'address' => is_string($address) ? $address : null,
                'city' => $city,
            ];
        } catch (\Throwable $e) {
            Log::warning('Nominatim reverse geocoding failed: '.$e->getMessage());

            return $empty;
        }
    }

    /**
     * Fill the empty fields of the primary result with the fallback's.
     *
     * @param  array{address: string|null, city: string|null, lat: float, lng: float}  $result
     * @param  array{address: string|null, city: string|null}  $fallback
     * @return array{address: string|null, city: string|null, lat: float, lng: float}
     */
    protected function merge(array $result, array $fallback): array
    {
        return [
            'address' => $result['address'] ?: $fallback['address'],
            'city' => $result['city'] ?: $fallback['city'],
            'lat' => $result['lat'],
            'lng' => $result['lng'],
        ];
    }
}
