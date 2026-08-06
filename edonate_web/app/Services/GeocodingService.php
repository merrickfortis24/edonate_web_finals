<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeocodingService
{
    public function geocodeAddress(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        try {
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

            return [
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
            ];
        } catch (Throwable $exception) {
            Log::warning('Geocoding request failed.', [
                'provider' => 'nominatim',
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function geocodeLocation(Location $location): ?array
    {
        $primary = $this->geocodeAddress($this->fullAddress($location));
        if ($primary !== null) {
            return $primary;
        }

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
