<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PrivacyCheck extends Command
{
    protected $signature = 'privacy:check';
    protected $description = 'Read-only privacy/security release checks; not legal certification.';

    public function handle(): int
    {
        $checks = [
            'Operator has reviewed the policies' => config('privacy.reviewed') === true,
            'Controller name configured' => trim((string) config('privacy.controller')) !== '',
            'Controller postal address configured' => trim((string) config('privacy.address')) !== '',
            'Privacy contact is a valid email address' => filter_var(config('privacy.contact'), FILTER_VALIDATE_EMAIL) !== false,
            'Privacy representative configured' => trim((string) config('privacy.representative')) !== '',
            'Service is limited to the Philippines' => config('privacy.philippines_only') === true,
            'Minimum account and donation age is at least 18' => (int) config('privacy.minimum_age') >= 18,
            'Production APP_ENV selected' => app()->isProduction(),
            'Debug output disabled' => config('app.debug') === false,
            'Application URL uses HTTPS' => str_starts_with((string) config('app.url'), 'https://'),
            'Secure session cookies enabled' => config('session.secure') === true,
            'HttpOnly session cookies enabled' => config('session.http_only') === true,
            'Server-side session payload encryption enabled' => config('session.encrypt') === true,
        ];
        try {
            $checks['Consent receipt migration installed'] = Schema::hasTable('privacy_receipts');

            $hasDonorBirthdate = Schema::hasTable('donors') && Schema::hasColumn('donors', 'birthdate');
            $checks['Donor birthdate field available'] = $hasDonorBirthdate;
            if ($hasDonorBirthdate) {
                $checks['No known underage donor accounts'] = ! DB::table('donors')
                    ->whereNotNull('birthdate')
                    ->whereDate('birthdate', '>', now()->subYears((int) config('privacy.minimum_age', 18))->toDateString())
                    ->exists();
            }
        } catch (Throwable) {
            $checks['Database/consent receipt table accessible'] = false;
        }
        foreach ($checks as $label => $ok) $this->line(($ok ? 'PASS ' : 'BLOCK ').$label);
        foreach (['firebase_sync_enabled', 'geocoding_enabled', 'ai_enabled'] as $integration) {
            $this->line((config('privacy.'.$integration) ? 'REVIEW enabled: ' : 'OFF ').$integration);
        }
        $this->warn('These checks cannot verify legal grounds, provider contracts, rights handling, retention, live hosting configuration, or full WCAG conformance.');
        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
