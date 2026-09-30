<?php

namespace App\Services;

use Throwable;

/**
 * Read-only lookup of public barangay polygon centers derived from the 2019
 * Faeldon TopoJSON dataset. Donor coordinates are never used here.
 */
class PhilippinesBarangayCenterIndex
{
    /** @var array<string, mixed>|null */
    private static ?array $indexes = null;

    /**
     * @return array{latitude: float, longitude: float, matched_barangay: string, match_type: string}|null
     */
    public function findCenter(?string $barangayCode, string $barangay, string $city, string $province): ?array
    {
        $indexes = $this->indexes();
        $code = strtoupper(trim((string) $barangayCode));
        if ($code !== '' && isset($indexes['by_code'][$code])) {
            return $this->centerResult($indexes['by_code'][$code], 'code');
        }

        $barangayKey = $this->normalizeName($barangay, true);
        $cityKey = $this->normalizeName($city);
        $provinceKey = $this->normalizeName($province);
        if ($barangayKey === '' || $cityKey === '' || $provinceKey === '') {
            return null;
        }

        $localityKey = $cityKey.'|'.$provinceKey;
        $candidates = $indexes['by_locality'][$localityKey] ?? [];
        if ($candidates === []) {
            return null;
        }

        $exact = array_values(array_filter(
            $candidates,
            fn (array $candidate): bool => $candidate['_normalized_barangay'] === $barangayKey
        ));
        if (count($exact) === 1) {
            return $this->centerResult($exact[0], 'exact_name');
        }
        if (count($exact) > 1) {
            return null;
        }

        // Permit only a unique, very close spelling match inside the exact
        // city/province. This handles minor entry typos without matching a
        // same-named barangay in a different municipality.
        $maximumDistance = mb_strlen($barangayKey) >= 8 ? 2 : 1;
        $bestDistance = $maximumDistance + 1;
        $best = [];
        foreach ($candidates as $candidate) {
            $distance = levenshtein($barangayKey, $candidate['_normalized_barangay']);
            if ($distance > $maximumDistance) {
                continue;
            }
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = [$candidate];
            } elseif ($distance === $bestDistance) {
                $best[] = $candidate;
            }
        }

        return count($best) === 1 ? $this->centerResult($best[0], 'near_name') : null;
    }

    /** @return array<string, mixed> */
    private function indexes(): array
    {
        if (self::$indexes !== null) {
            return self::$indexes;
        }

        $path = resource_path('data/philippines_barangay_centers_2019.json');
        if (! is_file($path)) {
            return self::$indexes = ['by_code' => [], 'by_locality' => []];
        }

        try {
            $rows = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return self::$indexes = ['by_code' => [], 'by_locality' => []];
        }

        $byCode = [];
        $byLocality = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row) || count($row) < 6) {
                continue;
            }

            [$code, $barangay, $city, $province, $latitude, $longitude] = $row;
            if (! is_string($code) || ! is_string($barangay) || ! is_string($city) || ! is_string($province)
                || ! is_numeric($latitude) || ! is_numeric($longitude)
                || ! $this->isPhilippinesCoordinate((float) $latitude, (float) $longitude)) {
                continue;
            }

            $entry = [
                'code' => strtoupper($code),
                'barangay' => $barangay,
                'city' => $city,
                'province' => $province,
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                '_normalized_barangay' => $this->normalizeName($barangay, true),
            ];
            $byCode[$entry['code']] = $entry;
            $localityKey = $this->normalizeName($city).'|'.$this->normalizeName($province);
            $byLocality[$localityKey][] = $entry;
        }

        return self::$indexes = ['by_code' => $byCode, 'by_locality' => $byLocality];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{latitude: float, longitude: float, matched_barangay: string, match_type: string}
     */
    private function centerResult(array $entry, string $matchType): array
    {
        return [
            'latitude' => $entry['latitude'],
            'longitude' => $entry['longitude'],
            'matched_barangay' => $entry['barangay'],
            'match_type' => $matchType,
        ];
    }

    private function normalizeName(string $value, bool $isBarangay = false): string
    {
        $value = mb_strtolower(trim($value));
        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($ascii !== false) {
                $value = $ascii;
            }
        }

        $value = preg_replace('/^city\s+of\s+/', '', $value) ?? $value;
        $value = preg_replace('/\s+city$/', '', $value) ?? $value;
        $value = preg_replace('/\s+\(capital\)$/', '', $value) ?? $value;
        if ($isBarangay) {
            $value = preg_replace('/^(?:barangay|brgy)\.?\s+/', '', $value) ?? $value;
        }

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value);
    }

    private function isPhilippinesCoordinate(float $latitude, float $longitude): bool
    {
        return $latitude >= 4 && $latitude <= 22 && $longitude >= 116 && $longitude <= 127;
    }
}
