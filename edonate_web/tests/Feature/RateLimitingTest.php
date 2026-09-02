<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('edonate.rate_limits', array_merge(
            (array) config('edonate.rate_limits', []),
            [
                'donor_api_per_minute' => 2,
                'public_api_per_minute' => 2,
                'donor_login_per_minute' => 2,
                'admin_login_per_minute' => 2,
                'login_ip_per_ten_minutes' => 2,
                'otp_send_per_ten_minutes' => 2,
                'otp_send_ip_per_hour' => 2,
                'otp_verify_per_ten_minutes' => 2,
                'password_reset_per_ten_minutes' => 2,
                'password_reset_ip_per_hour' => 2,
                'registration_per_hour' => 2,
                'eligibility_submit_per_ten_minutes' => 2,
                'verification_upload_per_ten_minutes' => 2,
                'appointment_write_per_minute' => 2,
                'blood_request_response_per_minute' => 2,
                'notification_send_per_minute' => 2,
                'map_api_per_minute' => 2,
                'reports_api_per_minute' => 2,
                'report_export_per_ten_minutes' => 2,
                'admin_api_per_minute' => 2,
                'admin_write_per_minute' => 2,
                'inventory_update_per_minute' => 2,
                'document_access_per_minute' => 2,
                'admin_2fa_per_ten_minutes' => 2,
                'admin_mfa_mobile_per_five_minutes' => 2,
            ]
        ));

        Route::middleware('web')->group(function (): void {
            Route::get('/__rate-limit/donor', fn () => response()->json(['ok' => true]))
                ->middleware('throttle:donor-api');
            Route::get('/__rate-limit/admin', fn () => response()->json(['ok' => true]))
                ->middleware('throttle:admin-api');
            Route::get('/__rate-limit/public', fn () => response()->json(['ok' => true]))
                ->middleware('throttle:public-api');
            Route::get('/__rate-limit/map', fn () => response()->json(['ok' => true]))
                ->middleware('throttle:map-api');
            Route::get('/__rate-limit/reports', fn () => response()->json(['ok' => true]))
                ->middleware('throttle:reports-api');
            Route::get('/__rate-limit/report-export', fn () => response()->json(['ok' => true]))
                ->middleware('throttle:report-export');
            Route::get('/__rate-limit/document', fn () => response()->json(['ok' => true]))
                ->middleware('throttle:document-access');

            foreach ([
                'donor-login',
                'admin-login',
                'admin-2fa',
                'admin-mfa-mobile',
                'otp-send',
                'otp-verify',
                'password-reset',
                'registration',
                'eligibility-submit',
                'verification-upload',
                'appointment-write',
                'blood-request-response',
                'notification-send',
                'admin-write',
                'inventory-update',
            ] as $limiter) {
                Route::post('/__rate-limit/'.$limiter, fn (Request $request) => response()->json([
                    'ok' => true,
                ]))->middleware('throttle:'.$limiter);
            }
        });
    }

    public function test_authenticated_donor_bucket_is_session_based_and_returns_json_429(): void
    {
        $session = ['donor_id' => 17];

        $this->withSession($session)->getJson('/__rate-limit/donor?donor_id=999')->assertOk();
        $this->withSession($session)->getJson('/__rate-limit/donor?donor_id=998')->assertOk();

        $this->withSession($session)
            ->getJson('/__rate-limit/donor?donor_id=1')
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
            ])
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit', '2');

        // A different authenticated donor gets a separate bucket, even on
        // the same IP and even if the request supplies another donor id.
        $this->withSession(['donor_id' => 18])
            ->getJson('/__rate-limit/donor?donor_id=17')
            ->assertOk();
    }

    public function test_admin_and_donor_namespaces_do_not_collide_and_public_is_ip_based(): void
    {
        $this->withSession(['admin_id' => 17])->getJson('/__rate-limit/admin')->assertOk();
        $this->withSession(['donor_id' => 17])->getJson('/__rate-limit/donor')->assertOk();

        $this->getJson('/__rate-limit/public')->assertOk();
        $this->getJson('/__rate-limit/public')->assertOk();
        $this->getJson('/__rate-limit/public')->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.24'])
            ->getJson('/__rate-limit/public')
            ->assertOk();
    }

    public function test_authentication_otp_registration_and_password_reset_limiters_are_separate(): void
    {
        $this->assertPostRouteThrottles('/__rate-limit/donor-login', ['email' => 'Donor@Example.Test']);
        $this->assertPostRouteThrottles('/__rate-limit/admin-login', ['email' => 'Admin@Example.Test']);
        $this->assertPostRouteThrottles('/__rate-limit/otp-send', ['email' => 'otp@example.test']);
        $this->assertPostRouteThrottles(
            '/__rate-limit/otp-verify',
            ['otp' => '000000'],
            ['pending_donor_signup' => ['payload' => ['email' => 'otp@example.test']]]
        );
        $this->assertPostRouteThrottles('/__rate-limit/password-reset', ['email' => 'reset@example.test']);
        $this->assertPostRouteThrottles('/__rate-limit/registration', ['email' => 'new@example.test']);
    }

    public function test_sensitive_donor_and_admin_action_limiters_return_429(): void
    {
        $this->assertPostRouteThrottles('/__rate-limit/eligibility-submit', [], ['donor_id' => 201]);
        $this->assertPostRouteThrottles('/__rate-limit/verification-upload', [], ['donor_id' => 201]);
        $this->assertPostRouteThrottles('/__rate-limit/appointment-write', [], ['donor_id' => 201]);
        $this->assertPostRouteThrottles('/__rate-limit/blood-request-response', [], ['donor_id' => 201]);
        $this->assertPostRouteThrottles('/__rate-limit/admin-2fa', [], [
            'admin_id' => 301,
            'pending_admin_2fa' => ['admin_id' => 301],
        ]);
        $this->assertPostRouteThrottles('/__rate-limit/notification-send', [], ['admin_id' => 301]);
        $this->assertPostRouteThrottles('/__rate-limit/admin-write', [], ['admin_id' => 301]);
        $this->assertPostRouteThrottles('/__rate-limit/inventory-update', [], ['admin_id' => 301]);

        $this->assertGetRouteThrottles('/__rate-limit/map', ['admin_id' => 301]);
        $this->assertGetRouteThrottles('/__rate-limit/reports', ['admin_id' => 301]);
        $this->assertGetRouteThrottles('/__rate-limit/report-export', ['admin_id' => 301]);
        $this->assertGetRouteThrottles('/__rate-limit/document', ['admin_id' => 301]);
    }

    public function test_production_routes_have_the_expected_named_throttles(): void
    {
        $expected = [
            'donor.signup.store' => 'throttle:registration',
            'donor.signup.check-email' => 'throttle:public-api',
            'donor.signup.send-otp' => 'throttle:otp-send',
            'donor.signup.confirm-otp' => 'throttle:otp-verify',
            'donor.login.store' => 'throttle:donor-login',
            'auth.google' => 'throttle:donor-login',
            'admin.login.store' => 'throttle:admin-login',
            'admin.2fa.verify' => 'throttle:admin-2fa',
            'admin.mfa.mobile' => 'throttle:public-api',
            'admin.mfa.mobile.approve' => 'throttle:admin-mfa-mobile',
            'admin.password.email' => 'throttle:password-reset',
            'donor.check-eligibility.submit' => 'throttle:eligibility-submit',
            'donor.verification.store' => 'throttle:verification-upload',
            'donor.book-appointment.store' => 'throttle:appointment-write',
            'donor.blood-requests.interested' => 'throttle:blood-request-response',
            'admin.notifications.store' => 'throttle:notification-send',
            'admin.notifications.show' => 'throttle:admin-api',
            'admin.map.data' => 'throttle:map-api',
            'admin.report-analytics.data' => 'throttle:reports-api',
            'admin.report-analytics.export' => 'throttle:report-export',
            'admin.appointments.data' => 'throttle:admin-api',
            'admin.blood-requests.store' => 'throttle:admin-write',
            'admin.facilities.inventory.update' => 'throttle:inventory-update',
            'admin.donor-verifications.document' => 'throttle:document-access',
            'admin.audit-logs.show' => 'throttle:admin-api',
        ];

        foreach ($expected as $routeName => $middleware) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route [{$routeName}] should exist.");
            $this->assertContains(
                $middleware,
                $route->middleware(),
                "Route [{$routeName}] should use [{$middleware}]."
            );
        }
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $session */
    private function assertPostRouteThrottles(string $uri, array $payload = [], array $session = []): void
    {
        $this->withSession($session)->postJson($uri, $payload)->assertOk();
        $this->withSession($session)->postJson($uri, $payload)->assertOk();
        $this->withSession($session)
            ->postJson($uri, $payload)
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    /** @param array<string, mixed> $session */
    private function assertGetRouteThrottles(string $uri, array $session = []): void
    {
        $this->withSession($session)->getJson($uri)->assertOk();
        $this->withSession($session)->getJson($uri)->assertOk();
        $this->withSession($session)
            ->getJson($uri)
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }
}
