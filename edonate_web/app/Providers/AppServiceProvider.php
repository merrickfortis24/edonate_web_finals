<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Donor;
use App\Models\DonorAuthentication;
use App\Models\DonationRecord;
use App\Models\EligibilityStatus;
use App\Models\Location;
use App\Models\Notification;
use App\Observers\AppointmentObserver;
use App\Observers\DonorAuthenticationObserver;
use App\Observers\DonorObserver;
use App\Observers\DonationRecordObserver;
use App\Observers\EligibilityStatusObserver;
use App\Observers\LocationObserver;
use App\Observers\NotificationObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Kreait\Firebase\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Factory::class, function ($app) {
            $serviceAccount = (string) config('services.firebase.credentials', '');
            $databaseUrl = (string) config('services.firebase.database_url', '');

            $factory = new Factory();
            if (!empty($serviceAccount)) {
                $factory = $factory->withServiceAccount($serviceAccount);
            }
            if (!empty($databaseUrl)) {
                $factory = $factory->withDatabaseUri($databaseUrl);
            }

            return $factory;
        });

        $this->app->singleton('firebase.auth', function ($app) {
            return $app->make(Factory::class)->createAuth();
        });

        $this->app->singleton('firebase.database', function ($app) {
            $databaseUrl = (string) config('services.firebase.database_url', '');
            if (empty($databaseUrl)) {
                return null;
            }
            return $app->make(Factory::class)->createDatabase();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRateLimiters();

        Donor::observe(DonorObserver::class);
        Location::observe(LocationObserver::class);
        Appointment::observe(AppointmentObserver::class);
        Notification::observe(NotificationObserver::class);
        DonationRecord::observe(DonationRecordObserver::class);
        EligibilityStatus::observe(EligibilityStatusObserver::class);
        DonorAuthentication::observe(DonorAuthenticationObserver::class);
    }

    /**
     * Register named Laravel limiters for application actions.
     *
     * Keys intentionally include an actor namespace so an admin id and a
     * donor id with the same numeric value never share a bucket. For session-
     * authenticated routes, the server-side session identity is always used;
     * request body/query ids are never used for throttling identity.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('donor-api', function (Request $request) {
            return Limit::perMinute($this->rateLimit('donor_api_per_minute', 60))
                ->by($this->donorKey($request));
        });

        RateLimiter::for('public-api', function (Request $request) {
            return Limit::perMinute($this->rateLimit('public_api_per_minute', 30))
                ->by('ip:'.$this->clientIp($request));
        });

        RateLimiter::for('donor-login', function (Request $request) {
            return $this->loginLimits($request, 'donor_login_per_minute');
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return $this->loginLimits($request, 'admin_login_per_minute');
        });

        RateLimiter::for('admin-2fa', function (Request $request) {
            $pending = $request->session()->get('pending_admin_2fa', []);
            $adminId = is_array($pending) ? (int) ($pending['admin_id'] ?? 0) : 0;
            $identity = $adminId > 0 ? 'admin:'.$adminId : 'ip:'.$this->clientIp($request);

            return Limit::perMinutes(10, $this->rateLimit('admin_2fa_per_ten_minutes', 5))
                ->by('admin-2fa|'.$identity.'|ip:'.$this->clientIp($request));
        });

        RateLimiter::for('otp-send', function (Request $request) {
            $email = $this->normalizedAccountIdentifier($request);
            $ip = $this->clientIp($request);

            return [
                Limit::perMinutes(10, $this->rateLimit('otp_send_per_ten_minutes', 3))
                    ->by('email:'.$this->keyPart($email).'|ip:'.$ip),
                Limit::perHour($this->rateLimit('otp_send_ip_per_hour', 10))
                    ->by('ip:'.$ip),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $pending = $request->session()->get('pending_donor_signup', []);
            $payload = is_array($pending) ? ($pending['payload'] ?? []) : [];
            $email = is_array($payload) ? (string) ($payload['email'] ?? '') : '';
            $email = $email !== '' ? Str::lower(trim($email)) : $this->normalizedAccountIdentifier($request);

            return Limit::perMinutes(10, $this->rateLimit('otp_verify_per_ten_minutes', 5))
                ->by('signup|email:'.$this->keyPart($email).'|ip:'.$this->clientIp($request));
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = $this->normalizedAccountIdentifier($request);
            $ip = $this->clientIp($request);

            return [
                Limit::perMinutes(10, $this->rateLimit('password_reset_per_ten_minutes', 3))
                    ->by('email:'.$this->keyPart($email).'|ip:'.$ip),
                Limit::perHour($this->rateLimit('password_reset_ip_per_hour', 10))
                    ->by('ip:'.$ip),
            ];
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perHour($this->rateLimit('registration_per_hour', 5))
                ->by('ip:'.$this->clientIp($request));
        });

        RateLimiter::for('eligibility-submit', function (Request $request) {
            return Limit::perMinutes(10, $this->rateLimit('eligibility_submit_per_ten_minutes', 10))
                ->by($this->donorKey($request).'|ip:'.$this->clientIp($request));
        });

        RateLimiter::for('verification-upload', function (Request $request) {
            return Limit::perMinutes(10, $this->rateLimit('verification_upload_per_ten_minutes', 5))
                ->by($this->donorKey($request).'|ip:'.$this->clientIp($request));
        });

        RateLimiter::for('appointment-write', function (Request $request) {
            return Limit::perMinute($this->rateLimit('appointment_write_per_minute', 10))
                ->by($this->donorKey($request).'|ip:'.$this->clientIp($request));
        });

        RateLimiter::for('blood-request-response', function (Request $request) {
            return Limit::perMinute($this->rateLimit('blood_request_response_per_minute', 10))
                ->by($this->donorKey($request).'|ip:'.$this->clientIp($request));
        });

        RateLimiter::for('notification-send', function (Request $request) {
            return Limit::perMinute($this->rateLimit('notification_send_per_minute', 10))
                ->by($this->adminKey($request));
        });

        RateLimiter::for('map-api', function (Request $request) {
            return Limit::perMinute($this->rateLimit('map_api_per_minute', 30))
                ->by($this->actorKey($request));
        });

        RateLimiter::for('reports-api', function (Request $request) {
            return Limit::perMinute($this->rateLimit('reports_api_per_minute', 20))
                ->by($this->adminKey($request));
        });

        RateLimiter::for('report-export', function (Request $request) {
            return Limit::perMinutes(10, $this->rateLimit('report_export_per_ten_minutes', 5))
                ->by($this->adminKey($request));
        });

        RateLimiter::for('admin-api', function (Request $request) {
            return Limit::perMinute($this->rateLimit('admin_api_per_minute', 120))
                ->by($this->adminKey($request));
        });

        RateLimiter::for('admin-write', function (Request $request) {
            return Limit::perMinute($this->rateLimit('admin_write_per_minute', 30))
                ->by($this->adminKey($request));
        });

        RateLimiter::for('inventory-update', function (Request $request) {
            return Limit::perMinute($this->rateLimit('inventory_update_per_minute', 20))
                ->by($this->adminKey($request));
        });

        RateLimiter::for('document-access', function (Request $request) {
            return Limit::perMinute($this->rateLimit('document_access_per_minute', 30))
                ->by($this->adminKey($request));
        });
    }

    /** @return array<int, Limit> */
    private function loginLimits(Request $request, string $accountLimitKey): array
    {
        $email = $this->normalizedAccountIdentifier($request);
        $ip = $this->clientIp($request);

        return [
            Limit::perMinute($this->rateLimit($accountLimitKey, 5))
                ->by('account:'.$this->keyPart($email).'|ip:'.$ip),
            Limit::perMinutes(10, $this->rateLimit('login_ip_per_ten_minutes', 20))
                ->by('ip:'.$ip),
        ];
    }

    private function rateLimit(string $key, int $fallback): int
    {
        return max(1, (int) config('edonate.rate_limits.'.$key, $fallback));
    }

    private function clientIp(Request $request): string
    {
        return $request->ip() ?: 'unknown';
    }

    private function normalizedAccountIdentifier(Request $request): string
    {
        $value = $request->input('email');

        if (! is_string($value) || trim($value) === '') {
            $value = $request->input('username', $request->input('login', ''));
        }

        return Str::lower(trim((string) $value));
    }

    private function keyPart(string $value): string
    {
        return $value !== '' ? $value : 'unknown';
    }

    private function donorKey(Request $request): string
    {
        $donorId = (int) $request->session()->get('donor_id', 0);

        return $donorId > 0
            ? 'donor:'.$donorId
            : 'ip:'.$this->clientIp($request);
    }

    private function adminKey(Request $request): string
    {
        $adminId = (int) $request->session()->get('admin_id', 0);

        return $adminId > 0
            ? 'admin:'.$adminId
            : 'ip:'.$this->clientIp($request);
    }

    private function actorKey(Request $request): string
    {
        $adminId = (int) $request->session()->get('admin_id', 0);
        if ($adminId > 0) {
            return 'admin:'.$adminId;
        }

        $donorId = (int) $request->session()->get('donor_id', 0);
        if ($donorId > 0) {
            return 'donor:'.$donorId;
        }

        return 'ip:'.$this->clientIp($request);
    }
}
