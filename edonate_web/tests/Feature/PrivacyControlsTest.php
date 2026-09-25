<?php

namespace Tests\Feature;

use App\Services\PrivacyConsent;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PrivacyControlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        (require database_path('migrations/2026_09_08_000000_create_privacy_receipts_table.php'))->up();
        config(['privacy.ai_enabled' => true]);
        Http::preventStrayRequests();
        Route::middleware('web')->post('/__test/personal-data', fn () => response()->json(['saved' => true]))->name('test.personal-data');
        // The route name contains a dot; store it as an array key, not a config path.
        config(['privacy.data_forms' => array_merge(config('privacy.data_forms'), ['test.personal-data' => 'health-screening'])]);
    }

    public function test_all_optional_processing_is_denied_without_current_authenticated_choice(): void
    {
        foreach ([null, 'garbage', json_encode(['version' => 'old', 'maps' => true, 'ai' => true, 'expires_at' => now()->addDay()->timestamp]),
            json_encode(['version' => config('privacy.version'), 'ai' => true, 'expires_at' => now()->subMinute()->timestamp])] as $cookie) {
            $request = Request::create('/', 'GET', [], [config('privacy.cookie') => $cookie]);
            $choices = app(PrivacyConsent::class)->choices($request);
            $this->assertFalse($choices['maps']);
            $this->assertFalse($choices['ai']);
            $this->assertFalse($choices['analytics']);
        }
    }

    public function test_choices_have_no_preselected_analytics_and_can_be_withdrawn(): void
    {
        $payload = ['version' => config('privacy.version'), 'maps' => true, 'ai' => true, 'analytics' => true];
        $response = $this->postJson(route('privacy.preferences'), $payload)
            ->assertOk()->assertJsonPath('choices.analytics', false)
            ->assertJsonPath('choices.maps', true)->assertCookie(config('privacy.cookie'));
        $cookie = collect($response->headers->getCookies())->first(fn ($item) => $item->getName() === config('privacy.cookie'));
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertStringNotContainsString('"maps":true', $cookie->getValue());
        $this->postJson(route('privacy.preferences'), array_replace($payload, ['maps' => false, 'ai' => false]))
            ->assertOk()->assertJsonPath('choices.maps', false)->assertJsonPath('choices.ai', false);
        $this->assertDatabaseCount('privacy_receipts', 2);
        $row = (array) DB::table('privacy_receipts')->first();
        $this->assertSame(64, strlen($row['subject_hash']));
        $this->assertArrayNotHasKey('ip_address', $row);
    }

    public function test_missing_rejected_or_stale_form_acknowledgments_are_rejected_server_side(): void
    {
        $this->postJson('/__test/personal-data', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['privacy_version', 'privacy_acknowledged', 'purpose_accepted']);
        $this->postJson('/__test/personal-data', [
            'privacy_version' => config('privacy.version'), 'privacy_acknowledged' => '0', 'purpose_accepted' => '0',
        ])->assertUnprocessable();
        $this->postJson('/__test/personal-data', [
            'privacy_version' => 'old', 'privacy_acknowledged' => '1', 'purpose_accepted' => '1',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('privacy_receipts', 0);
        $this->postJson('/__test/personal-data', [
            'privacy_version' => config('privacy.version'), 'privacy_acknowledged' => '1', 'purpose_accepted' => '1',
        ])->assertOk()->assertJsonPath('saved', true);
        $this->assertDatabaseCount('privacy_receipts', 1);
    }

    public function test_preferences_require_csrf_and_reject_stale_versions(): void
    {
        $this->postJson(route('privacy.preferences'), ['version' => 'old', 'maps' => true, 'ai' => true, 'analytics' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->app->instance('env', 'production');
        $this->postJson(route('privacy.preferences'), ['version' => config('privacy.version'), 'maps' => true, 'ai' => true, 'analytics' => false])
            ->assertStatus(419);
        $this->assertDatabaseCount('privacy_receipts', 0);
    }

    public function test_policies_render_and_success_responses_have_security_headers(): void
    {
        foreach (['privacy', 'terms', 'cookies'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Privacy choices')
                ->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY');
        }
    }

    public function test_optional_replication_and_geocoding_do_not_send_by_default(): void
    {
        config(['privacy.firebase_sync_enabled' => false, 'privacy.geocoding_enabled' => false]);
        $this->assertNull(app('firebase.database'));
        Http::fake();
        $this->assertNull(app(GeocodingService::class)->geocodeAddress('Private street address'));
        Http::assertNothingSent();
    }

    public function test_web_deployment_shell_endpoint_has_been_retired(): void
    {
        $this->postJson('/git-deploy-token-734866278')->assertNotFound();
    }

    public function test_production_collection_requires_reviewed_controller_details(): void
    {
        $this->app->instance('env', 'production');
        config(['privacy.reviewed' => false, 'privacy.controller' => '', 'privacy.address' => '', 'privacy.contact' => '']);
        $consent = app(PrivacyConsent::class);
        $this->assertFalse($consent->readyForCollection());
        config(['privacy.reviewed' => true, 'privacy.controller' => 'Test Operator', 'privacy.address' => 'Test Address', 'privacy.contact' => 'privacy@example.test']);
        $this->assertTrue($consent->readyForCollection());
    }
}
