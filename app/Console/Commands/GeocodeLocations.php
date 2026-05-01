<?php

namespace App\Console\Commands;

use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodeLocations extends Command
{
    protected $signature   = 'geocode:locations {--force : Re-geocode locations that already have coordinates}';
    protected $description = 'Populate NULL latitude/longitude on the locations table using the Nominatim API';

    private const NOMINATIM_URL  = 'https://nominatim.openstreetmap.org/search';
    private const RATE_LIMIT_SEC = 1;

    public function handle(): int
    {
        $query = $this->option('force')
            ? Location::query()
            : Location::query()->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });

        $locations = $query->get();

        if ($locations->isEmpty()) {
            $this->info('All locations already have coordinates. Use --force to re-geocode.');
            return self::SUCCESS;
        }

        $this->info("Geocoding {$locations->count()} location(s)…");
        $bar = $this->output->createProgressBar($locations->count());
        $bar->start();

        $succeeded = 0;
        $failed    = 0;

        foreach ($locations as $location) {
            $result = $this->geocode($location);

            if ($result) {
                $location->update([
                    'latitude'  => $result['lat'],
                    'longitude' => $result['lon'],
                ]);
                $succeeded++;
            } else {
                $this->newLine();
                $this->warn("  ✗ Could not geocode location_id={$location->location_id}: {$this->buildQuery($location)}");
                $failed++;
            }

            $bar->advance();
            sleep(self::RATE_LIMIT_SEC);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Succeeded: {$succeeded} | Failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function geocode(Location $location): ?array
    {
        $searchQuery = $this->buildQuery($location);

        try {
            $response = Http::withHeaders(['User-Agent' => 'eDonate-CapstoneProject/1.0 (fortismerrick@gmail.com)'])
                ->timeout(10)
                ->get(self::NOMINATIM_URL, [
                    'q'              => $searchQuery,
                    'format'         => 'json',
                    'limit'          => 1,
                    'addressdetails' => 0,
                ]);

            if (! $response->ok()) {
                return null;
            }

            $hits = $response->json();

            if (empty($hits)) {
                // Retry with a broader query (drop street, keep barangay + city + country)
                return $this->geocodeFallback($location);
            }

            return ['lat' => (float) $hits[0]['lat'], 'lon' => (float) $hits[0]['lon']];
        } catch (\Throwable) {
            return null;
        }
    }

    private function geocodeFallback(Location $location): ?array
    {
        $fallbackQuery = implode(', ', array_filter([
            $location->barangay_name,
            $location->city,
            $location->province,
            'Philippines',
        ]));

        try {
            $response = Http::withHeaders(['User-Agent' => 'eDonate-CapstoneProject/1.0'])
                ->timeout(10)
                ->get(self::NOMINATIM_URL, [
                    'q'      => $fallbackQuery,
                    'format' => 'json',
                    'limit'  => 1,
                ]);

            $hits = $response->ok() ? $response->json() : [];

            if (empty($hits)) {
                return null;
            }

            return ['lat' => (float) $hits[0]['lat'], 'lon' => (float) $hits[0]['lon']];
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildQuery(Location $location): string
    {
        return implode(', ', array_filter([
            $location->street_address,
            $location->barangay_name,
            $location->city,
            $location->province,
            'Philippines',
        ]));
    }
}
