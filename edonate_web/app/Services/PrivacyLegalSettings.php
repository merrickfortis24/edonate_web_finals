<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PrivacyLegalSettings
{
    public const POLICY_MAX_LENGTH = 15000;

    private const TABLE = 'system_settings';

    private const STORAGE_KEYS = [
        'privacyPolicy' => 'privacy_policy',
        'termsAndConditions' => 'terms_and_conditions',
        'cookiePolicy' => 'cookie_policy',
        'enforceCookieConsentBanner' => 'enforce_cookie_consent_banner',
    ];

    /** @var array{privacyPolicy:?string,termsAndConditions:?string,cookiePolicy:?string,enforceCookieConsentBanner:bool}|null */
    private ?array $resolved = null;

    /**
     * Resolve the public legal configuration. Missing or unavailable storage
     * fails safely to the reviewed built-in policies and an enforced banner.
     *
     * @return array{privacyPolicy:?string,termsAndConditions:?string,cookiePolicy:?string,enforceCookieConsentBanner:bool}
     */
    public function values(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $defaults = $this->defaults();
        try {
            $rows = DB::table(self::TABLE)
                ->whereIn('setting_key', array_values(self::STORAGE_KEYS))
                ->pluck('setting_value', 'setting_key');
        } catch (Throwable) {
            return $this->resolved = $defaults;
        }

        return $this->resolved = [
            'privacyPolicy' => $this->normalizePolicy($rows[self::STORAGE_KEYS['privacyPolicy']] ?? null),
            'termsAndConditions' => $this->normalizePolicy($rows[self::STORAGE_KEYS['termsAndConditions']] ?? null),
            'cookiePolicy' => $this->normalizePolicy($rows[self::STORAGE_KEYS['cookiePolicy']] ?? null),
            'enforceCookieConsentBanner' => $this->parseEnforcementFlag(
                $rows[self::STORAGE_KEYS['enforceCookieConsentBanner']] ?? '1'
            ),
        ];
    }

    public function policy(string $document): ?string
    {
        $key = match ($document) {
            'privacy' => 'privacyPolicy',
            'terms' => 'termsAndConditions',
            'cookies' => 'cookiePolicy',
            default => null,
        };

        return $key === null ? null : $this->values()[$key];
    }

    public function enforceCookieConsentBanner(): bool
    {
        return $this->values()['enforceCookieConsentBanner'];
    }

    public function storageAvailable(): bool
    {
        try {
            return Schema::hasTable(self::TABLE)
                && Schema::hasColumn(self::TABLE, 'setting_key')
                && Schema::hasColumn(self::TABLE, 'setting_value');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array{privacy_policy?:mixed,terms_and_conditions?:mixed,cookie_policy?:mixed,enforce_cookie_consent_banner?:mixed}  $input
     * @return array{privacyPolicy:?string,termsAndConditions:?string,cookiePolicy:?string,enforceCookieConsentBanner:bool}
     */
    public function save(array $input): array
    {
        $settings = [
            self::STORAGE_KEYS['privacyPolicy'] => $this->normalizePolicy($input['privacy_policy'] ?? null),
            self::STORAGE_KEYS['termsAndConditions'] => $this->normalizePolicy($input['terms_and_conditions'] ?? null),
            self::STORAGE_KEYS['cookiePolicy'] => $this->normalizePolicy($input['cookie_policy'] ?? null),
            self::STORAGE_KEYS['enforceCookieConsentBanner'] => $this->parseEnforcementFlag(
                $input['enforce_cookie_consent_banner'] ?? false
            ) ? '1' : '0',
        ];

        DB::transaction(function () use ($settings): void {
            foreach ($settings as $key => $value) {
                DB::table(self::TABLE)->updateOrInsert(
                    ['setting_key' => $key],
                    [
                        'setting_value' => $value,
                        'updated_at' => now(),
                    ]
                );
            }
        });

        $this->resolved = null;

        return $this->values();
    }

    /**
     * @return array{privacyPolicy:null,termsAndConditions:null,cookiePolicy:null,enforceCookieConsentBanner:true}
     */
    private function defaults(): array
    {
        return [
            'privacyPolicy' => null,
            'termsAndConditions' => null,
            'cookiePolicy' => null,
            'enforceCookieConsentBanner' => true,
        ];
    }

    private function normalizePolicy(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));

        return $normalized === '' ? null : $normalized;
    }

    private function parseEnforcementFlag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return ! in_array(strtolower(trim((string) $value)), ['0', 'false', 'no', 'off'], true);
    }
}
