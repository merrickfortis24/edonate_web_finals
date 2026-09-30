<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeocodingService
{
    /**
     * Return a privacy-reduced Nominatim search response for an explicitly
     * requested barangay center. Queries/results are cached, and the shared
     * provider lock serializes traffic from this application on one server.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchBarangayArea(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $cacheKey = 'geocoding:nominatim:barangay:'.hash('sha256', mb_strtolower($query));
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey, []);
        }

        // The same shared key is used by automatic geocoding. Waiting here
        // keeps this user-initiated one-off queue below the public API limit
        // even if two admins or another app request run at the same time.
        $permit = false;
        for ($attempt = 0; $attempt < 48; $attempt++) {
            if (Cache::add('geocoding:provider-request', true, 2)) {
                $permit = true;
                break;
            }
            usleep(250_000);
        }

        if (! $permit) {
            Log::warning('Barangay geocoding queue could not acquire the provider rate-limit lock.');

            return [];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent(),
            ])
                ->acceptJson()
                ->timeout($this->timeout())
                ->get($this->endpoint(), [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'addressdetails' => 1,
                    'countrycodes' => 'ph',
                ]);

            if (! $response->ok()) {
                Log::warning('Barangay geocoding provider returned a non-success response.', [
                    'provider' => 'nominatim',
                    'status' => $response->status(),
                ]);

                return [];
            }

            $hits = $response->json();
            $safeAddressFields = array_fill_keys([
                'city', 'town', 'municipality', 'village', 'hamlet', 'suburb',
                'neighbourhood', 'city_district', 'county', 'state_district',
                'state', 'country', 'country_code',
            ], true);
            $results = [];

            if (is_array($hits)) {
                foreach ($hits as $hit) {
                    if (! is_array($hit)
                        || ! $this->validCoordinate($hit['lat'] ?? null, $hit['lon'] ?? null)
                        || ! is_array($hit['boundingbox'] ?? null)
                        || count($hit['boundingbox']) !== 4) {
                        continue;
                    }

                    $address = array_intersect_key((array) ($hit['address'] ?? []), $safeAddressFields);
                    $results[] = [
                        'boundingbox' => array_map('strval', $hit['boundingbox']),
                        'lat' => (string) $hit['lat'],
                        'lon' => (string) $hit['lon'],
                        // Do not forward Nominatim street/house-level labels.
                        'display_name' => implode(', ', array_filter(array_map('strval', $address))),
                        'address' => $address,
                    ];
                }
            }

            Cache::put($cacheKey, $results, now()->addDays(30));

            return $results;
        } catch (Throwable $exception) {
            Log::warning('Barangay geocoding request failed.', [
                'provider' => 'nominatim',
                'type' => $exception::class,
            ]);

            return [];
        }
    }

    /**
     * Confirm that a coordinate being persisted came from this locality's
     * cached Nominatim search result and still names the requested barangay
     * and city. This prevents a modified browser payload from inventing a pin.
     */
    public function isCachedBarangayAreaCoordinate(string $query, string $barangay, string $city, mixed $latitude, mixed $longitude): bool
    {
        $cacheKey = 'geocoding:nominatim:barangay:'.hash('sha256', mb_strtolower(trim($query)));
        $results = Cache::get($cacheKey);
        if (! is_array($results) || ! $this->validCoordinate($latitude, $longitude)) {
            return false;
        }

        $expectedBarangay = $this->normalizeLocality($barangay);
        $expectedCity = $this->normalizeLocality($city);
        if ($expectedBarangay === '' || $expectedCity === '') {
            return false;
        }

        foreach ($results as $result) {
            $lat = (float) ($result['lat'] ?? NAN);
            $lng = (float) ($result['lon'] ?? NAN);
            $address = is_array($result['address'] ?? null) ? $result['address'] : [];
            $localityText = $this->normalizeLocality(implode(' ', array_map('strval', [
                $result['display_name'] ?? '',
                ...array_values($address),
            ])));

            if (abs($lat - (float) $latitude) <= 0.000001
                && abs($lng - (float) $longitude) <= 0.000001
                && str_contains($localityText, $expectedBarangay)
                && str_contains($localityText, $expectedCity)) {
                return true;
            }
        }

        return false;
    }

    public function geocodeAddress(string $address): ?array
    {
        if (! config('privacy.geocoding_enabled') || ! app(PrivacyConsent::class)->readyForCollection()) {
            return null;
        }
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        try {
            $cacheKey = 'geocoding:area:'.hash('sha256', $address);
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            // One provider request per second across this application's workers.
            if (! Cache::add('geocoding:provider-request', true, 2)) {
                return null;
            }
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent(),
            ])
                ->acceptJson()
                ->timeout($this->timeout())
                ->get($this->endpoint(), [
                    'q' => $address,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'addressdetails' => 0,
                    'countrycodes' => 'ph',
                ]);

            if (! $response->ok()) {
                Log::warning('Geocoding provider returned a non-success response.', [
                    'provider' => 'nominatim',
                    'status' => $response->status(),
                ]);

                return null;
            }

            $hits = $response->json();
            if (! is_array($hits) || $hits === []) {
                return null;
            }

            $first = $hits[0] ?? [];
            $lat = $first['lat'] ?? null;
            $lng = $first['lon'] ?? null;

            if (! $this->validCoordinate($lat, $lng)) {
                return null;
            }

            $coordinates = [
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
            ];
            Cache::put($cacheKey, $coordinates, now()->addDays(30));

            return $coordinates;
        } catch (Throwable $exception) {
            Log::warning('Geocoding request failed.', [
                'provider' => 'nominatim',
                'type' => $exception::class,
            ]);

            return null;
        }
    }

    public function geocodeLocation(Location $location): ?array
    {
        // Never send a donor's street address to the public geocoding provider.
        return $this->geocodeAddress($this->areaAddress($location));
    }

    public function geocodeAndSave(Location $location, bool $force = false): bool
    {
        if (! $force && $this->hasCoordinates($location)) {
            return false;
        }

        $coordinates = $this->geocodeLocation($location);
        if ($coordinates === null) {
            return false;
        }

        $location->forceFill($coordinates)->save();

        return true;
    }

    public function hasCoordinates(?Location $location): bool
    {
        if (! $location) {
            return false;
        }

        return $this->validCoordinate($location->latitude, $location->longitude);
    }

    public function clearCoordinates(Location $location): void
    {
        $location->forceFill([
            'latitude' => null,
            'longitude' => null,
        ])->save();
    }

    public function validCoordinate(mixed $latitude, mixed $longitude): bool
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        return $lat >= -90
            && $lat <= 90
            && $lng >= -180
            && $lng <= 180
            && ! ($lat === 0.0 && $lng === 0.0);
    }

    public function fullAddress(Location $location): string
    {
        return $this->joinAddressParts([
            $location->street_address,
            $location->barangay_name,
            $location->city,
            $location->province,
            'Philippines',
        ]);
    }

    public function areaAddress(Location $location): string
    {
        return $this->joinAddressParts([
            $location->barangay_name,
            $location->city,
            $location->province,
            'Philippines',
        ]);
    }

    private function joinAddressParts(array $parts): string
    {
        return implode(', ', array_filter(array_map(
            fn (mixed $part): string => trim((string) ($part ?? '')),
            $parts
        )));
    }

    private function normalizeLocality(string $value): string
    {
        $value = \Illuminate\Support\Str::ascii(trim($value));
        $value = preg_replace('/^(?:barangay|brgy)\\.?\\s+/i', '', $value) ?? $value;
        $value = preg_replace('/^city\\s+of\\s+/i', '', $value) ?? $value;

        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $value) ?? '');
    }

    private function endpoint(): string
    {
        return (string) config('services.geocoding.nominatim_url', 'https://nominatim.openstreetmap.org/search');
    }

    private function userAgent(): string
    {
        return (string) config('services.geocoding.user_agent', 'eDonate-CapstoneProject/1.0 (fortismerrick@gmail.com)');
    }

    private function timeout(): int
    {
        return max(1, (int) config('services.geocoding.timeout', 10));
    }
}
