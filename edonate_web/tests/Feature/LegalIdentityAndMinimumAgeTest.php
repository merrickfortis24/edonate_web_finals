<?php

namespace Tests\Feature;

use App\Http\Requests\StoreDonorRegistrationRequest;
use App\Services\FirebaseGoogleIdentityService;
use App\Services\PrivacyConsent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class LegalIdentityAndMinimumAgeTest extends TestCase
{
    public function test_public_policies_publish_the_owner_supplied_identity_and_scope(): void
    {
        foreach (['privacy', 'terms', 'cookies'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSeeText('City Health Office of Lipa City')
                ->assertSeeText('City Hall Compound, Lipa City, Batangas, Philippines')
                ->assertSeeText('John Merrick F. Fortis')
                ->assertSeeText('Philippines only')
                ->assertSeeText('18 years old')
                ->assertSee('mailto:fortismerrick@gmail.com', false);
        }
    }

    public function test_registration_birthdate_rule_enforces_the_18_year_minimum(): void
    {
        config(['privacy.minimum_age' => 18]);
        $birthdateRules = (new StoreDonorRegistrationRequest)->rules()['birthdate'];

        $exactlyEighteen = Validator::make([
            'birthdate' => now()->subYears(18)->toDateString(),
        ], ['birthdate' => $birthdateRules]);

        $underEighteen = Validator::make([
            'birthdate' => now()->subYears(18)->addDay()->toDateString(),
        ], ['birthdate' => $birthdateRules]);

        $this->assertFalse($exactlyEighteen->fails());
        $this->assertTrue($underEighteen->fails());
        $this->assertArrayHasKey('birthdate', $underEighteen->errors()->toArray());
    }

    public function test_production_collection_requires_the_representative_and_owner_approved_scope(): void
    {
        $this->app->instance('env', 'production');
        config([
            'privacy.reviewed' => true,
            'privacy.controller' => 'City Health Office of Lipa City',
            'privacy.address' => 'City Hall Compound, Lipa City, Batangas, Philippines',
            'privacy.contact' => 'fortismerrick@gmail.com',
            'privacy.representative' => '',
            'privacy.philippines_only' => true,
            'privacy.minimum_age' => 18,
        ]);

        $this->assertFalse(app(PrivacyConsent::class)->readyForCollection());

        config(['privacy.representative' => 'John Merrick F. Fortis']);
        $this->assertTrue(app(PrivacyConsent::class)->readyForCollection());

        config(['privacy.minimum_age' => 17]);
        $this->assertFalse(app(PrivacyConsent::class)->readyForCollection());
    }

    public function test_google_account_flow_discloses_the_age_attestation(): void
    {
        $this->get(route('donor.login'))
            ->assertOk()
            ->assertSee('id="googleAgeConfirmed"', false)
            ->assertSee('Select Sign in with Google once to choose your account.')
            ->assertSee('signInWithRedirect')
            ->assertSee('getRedirectResult')
            ->assertSeeText('If Google creates a new eDonate account, I confirm that I am at least 18 years old.');
    }

    public function test_new_google_account_is_rejected_without_the_18_plus_attestation(): void
    {
        Schema::create('donor_authentication', function (Blueprint $table): void {
            $table->increments('auth_id');
            $table->unsignedInteger('donor_id');
            $table->string('email')->unique();
        });

        $identity = Mockery::mock(FirebaseGoogleIdentityService::class);
        $identity->shouldReceive('verify')->once()->andReturn([
            'uid' => 'adult-policy-test-uid',
            'email' => 'new-google-user@example.test',
            'email_verified' => true,
            'provider' => 'google.com',
            'name' => 'New Google User',
        ]);
        $this->app->instance(FirebaseGoogleIdentityService::class, $identity);

        $this->postJson(route('auth.google'), [
            'id_token' => 'verified-test-token',
            'uid' => 'adult-policy-test-uid',
            'email' => 'new-google-user@example.test',
            'terms_accepted' => true,
            'age_confirmed' => false,
            'privacy_version' => config('privacy.version'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('age_confirmed');
    }

    public function test_release_preflight_accepts_complete_reviewed_production_configuration(): void
    {
        (require database_path('migrations/2026_09_08_000000_create_privacy_receipts_table.php'))->up();
        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->date('birthdate')->nullable();
        });
        DB::table('donors')->insert([
            'birthdate' => now()->subYears(25)->toDateString(),
        ]);
        $this->app->instance('env', 'production');
        config([
            'privacy.reviewed' => true,
            'privacy.controller' => 'City Health Office of Lipa City',
            'privacy.address' => 'City Hall Compound, Lipa City, Batangas, Philippines',
            'privacy.contact' => 'fortismerrick@gmail.com',
            'privacy.representative' => 'John Merrick F. Fortis',
            'privacy.philippines_only' => true,
            'privacy.minimum_age' => 18,
            'app.debug' => false,
            'app.url' => 'https://edonate.online',
            'session.secure' => true,
            'session.http_only' => true,
            'session.encrypt' => true,
        ]);

        $this->artisan('privacy:check')
            ->expectsOutputToContain('PASS Privacy representative configured')
            ->expectsOutputToContain('PASS Minimum account and donation age is at least 18')
            ->expectsOutputToContain('PASS No known underage donor accounts')
            ->assertSuccessful();
    }

    public function test_known_underage_donor_is_denied_by_the_active_account_middleware(): void
    {
        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->boolean('is_active')->default(true);
            $table->date('birthdate')->nullable();
        });
        $donorId = (int) DB::table('donors')->insertGetId([
            'is_active' => true,
            'birthdate' => now()->subYears(18)->addDay()->toDateString(),
        ], 'donor_id');

        Route::middleware(['web', 'donor.active'])
            ->get('/test/minimum-age-gate', fn () => response()->json(['ok' => true]));

        $this->withSession(['donor_id' => $donorId])
            ->getJson('/test/minimum-age-gate')
            ->assertForbidden()
            ->assertJsonPath('message', 'This donor account does not meet the minimum age requirement.');
    }
}
