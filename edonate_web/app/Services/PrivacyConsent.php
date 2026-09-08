<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrivacyConsent
{
    public function readyForCollection(): bool
    {
        return ! app()->isProduction() || (
            config('privacy.reviewed') === true
            && trim((string) config('privacy.controller')) !== ''
            && trim((string) config('privacy.address')) !== ''
            && filter_var(config('privacy.contact'), FILTER_VALIDATE_EMAIL) !== false
            && trim((string) config('privacy.representative')) !== ''
            && config('privacy.philippines_only') === true
            && (int) config('privacy.minimum_age') >= 18
        );
    }

    public function choices(Request $request): array
    {
        // Laravel's EncryptCookies middleware authenticates this HttpOnly cookie.
        $value = json_decode((string) $request->cookie(config('privacy.cookie'), ''), true);
        $valid = $this->readyForCollection() && is_array($value)
            && ($value['version'] ?? null) === config('privacy.version')
            && is_int($value['expires_at'] ?? null)
            && $value['expires_at'] > now()->timestamp;

        return [
            'decided' => $valid,
            'necessary' => true,
            'analytics' => $valid && config('privacy.analytics_enabled') && ($value['analytics'] ?? false) === true,
            'maps' => $valid && ($value['maps'] ?? false) === true,
            'ai' => $valid && config('privacy.ai_enabled') && ($value['ai'] ?? false) === true,
            'expires_at' => $valid ? $value['expires_at'] : 0,
        ];
    }

    public function record(Request $request, string $purpose, array $choices, ?string $verifiedSubject = null): void
    {
        $subject = $request->session()->get('privacy_subject');
        if (! is_string($subject)) {
            $subject = (string) Str::uuid();
            $request->session()->put('privacy_subject', $subject);
        }
        // Account-linked receipts use a keyed pseudonym, never a raw account ID/email.
        $subject = $verifiedSubject
            ?? ($request->session()->has('donor_id') ? 'donor:'.$request->session()->get('donor_id') : $subject);
        if ($purpose !== 'optional-services') {
            $choices['statement'] = config('privacy.purposes.'.$purpose);
            $choices['notice'] = 'Terms agreement and Privacy Policy acknowledgment';
        }
        // No IP address, user-agent, health answer, document, or raw session ID.
        DB::table('privacy_receipts')->insert([
            'subject_hash' => hash_hmac('sha256', $subject, (string) config('app.key')),
            'policy_version' => config('privacy.version'),
            'purpose' => $purpose,
            'choices' => json_encode($choices, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
