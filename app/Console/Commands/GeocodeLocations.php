<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

class GeocodeLocations extends Command
{
    protected $signature = 'geocode:locations
        {--force : Re-geocode locations that already have coordinates}
        {--limit= : Maximum number of locations to process}';

    protected $description = 'Populate missing latitude/longitude on the locations table using the configured geocoding provider';

    private const RATE_LIMIT_SEC = 1;

    public function handle(GeocodingService $geocoding): int
    {
        $query = $this->option('force')
            ? Location::query()
            : Location::query()->where(function ($q): void {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });

        $limit = (int) ($this->option('limit') ?: 0);
        if ($limit > 0) {
            $query->limit($limit);
        }

        $locations = $query->orderBy('location_id')->get();

        if ($locations->isEmpty()) {
            $this->info('All locations already have coordinates. Use --force to re-geocode.');

            return self::SUCCESS;
        }

        $this->info("Geocoding {$locations->count()} location(s)...");
        $bar = $this->output->createProgressBar($locations->count());
        $bar->start();

        $succeeded = 0;
        $failed = 0;

        foreach ($locations as $location) {
            if ($geocoding->geocodeAndSave($location, (bool) $this->option('force'))) {
                $succeeded++;
            } else {
                $this->newLine();
                $this->warn("  Could not geocode location_id={$location->location_id}");
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
}
