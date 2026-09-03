<?php

namespace Tests\Feature;

use App\Services\FirebaseGoogleIdentityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AdminGoogleLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.firebase.admin_google_login_enabled', true);
        config()->set('services.firebase.database_url', '');

        Schema::dropIfExists('admin_security_settings');
        Schema::dropIfExists('admins');

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('username', 100);
            $table->string('email', 150);
            $table->string('password');
            $table->string('full_name', 150);
            $table->string('role', 30)->default('admin');
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->json('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_last_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('remember_token_expires_at')->nullable();
        });

        Schema::create('admin_security_settings', function (Blueprint $table): void {
            $table->bigIncrements('admin_security_setting_id');
            $table->boolean('enforce_two_factor')->default(false);
            $table->unsignedInteger('session_timeout_minutes')->default(10);
            $table->timestamps();
        });

        DB::table('admin_security_settings')->insert([
            'enforce_two_factor' => false,
            'session_timeout_minutes' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admins')->insert([
            'admin_id' => 1,
            'username' => 'test-admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('not-used-by-google-login-test'),
            'full_name' => 'Test Admin',
            'role' => 'admin',
            'two_factor_enabled' => false,
        ]);
    }

    public function test_google_login_uses_verified_google_identity_and_existing_admin_account(): void
    {
        $this->mockIdentity([
            'uid' => 'google-admin-uid',
            'email' => 'ADMIN@example.test',
            'name' => 'Test Admin',
            'email_verified' => true,
            'provider' => 'google.com',
        ]);

        $this->postJson(route('admin.login.google'), [
            'id_token' => 'verified-firebase-token',
            'email' => 'attacker-controlled@example.test',
            'uid' => 'attacker-controlled-uid',
            'name' => 'Attacker Controlled Name',
            'remember' => false,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Google sign-in successful.')
            ->assertJsonPath('redirect_url', route('admin.dashboard'));

        $this->assertSame(1, (int) session('admin_id'));
        $this->assertSame('admin', session('admin_role'));
    }

    public function test_admin_login_page_exposes_the_google_sign_in_option(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('rel="icon" type="image/png"', false)
            ->assertSee('images/edonate-icon.png?v=', false)
            ->assertSee(str_replace('/', '\\/', route('admin.login.google')), false)
            ->assertSee('Google may ask you to confirm this sign-in on your phone.');
    }

    public function test_google_login_rejects_an_identity_that_is_not_a_google_account(): void
    {
        $this->mockIdentity([
            'uid' => 'password-provider-uid',
            'email' => 'admin@example.test',
            'name' => 'Test Admin',
            'email_verified' => true,
            'provider' => 'password',
        ]);

        $this->postJson(route('admin.login.google'), ['id_token' => 'not-google-token'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Please use a verified Google account for admin sign-in.');

        $this->assertNull(session('admin_id'));
    }

    public function test_google_login_rejects_an_unverified_google_email(): void
    {
        $this->mockIdentity([
            'uid' => 'google-admin-uid',
            'email' => 'admin@example.test',
            'name' => 'Test Admin',
            'email_verified' => false,
            'provider' => 'google.com',
        ]);

        $this->postJson(route('admin.login.google'), ['id_token' => 'unverified-token'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Please use a verified Google account for admin sign-in.');

        $this->assertNull(session('admin_id'));
    }

    public function test_google_login_does_not_create_an_admin_for_an_unknown_google_email(): void
    {
        $this->mockIdentity([
            'uid' => 'unknown-google-uid',
            'email' => 'unknown@example.test',
            'name' => 'Unknown User',
            'email_verified' => true,
            'provider' => 'google.com',
        ]);

        $this->postJson(route('admin.login.google'), [
            'id_token' => 'unknown-token',
            'email' => 'admin@example.test',
            'uid' => 'google-admin-uid',
            'name' => 'Test Admin',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This Google account is not authorized to access the admin portal.');

        $this->assertSame(1, DB::table('admins')->count());
        $this->assertNull(session('admin_id'));
    }

    public function test_google_login_rejects_an_invalid_firebase_token(): void
    {
        Log::spy();

        $mock = Mockery::mock(FirebaseGoogleIdentityService::class);
        $mock->shouldReceive('verify')
            ->once()
            ->with('invalid-firebase-token')
            ->andThrow(new FailedToVerifyToken('The JWT string must have two dots'));
        $this->app->instance(FirebaseGoogleIdentityService::class, $mock);

        $this->postJson(route('admin.login.google'), [
            'id_token' => 'invalid-firebase-token',
            'email' => 'admin@example.test',
            'uid' => 'google-admin-uid',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Google sign-in could not be verified. Please try again.');

        $this->assertNull(session('admin_id'));
        Log::shouldHaveReceived('warning')
            ->with('Admin Google sign-in token verification failed.', Mockery::on(
                fn (array $context): bool => ($context['failure_type'] ?? null) === 'invalid_firebase_token'
                    && ! array_key_exists('id_token', $context)
            ));
    }

    public function test_firebase_audit_failure_does_not_block_authorized_google_login(): void
    {
        Log::spy();

        $database = Mockery::mock();
        $database->shouldReceive('getReference')
            ->once()
            ->andThrow(new RuntimeException('simulated audit transport failure'));
        $this->app->instance('firebase.database', $database);

        $this->mockIdentity([
            'uid' => 'google-admin-uid',
            'email' => 'admin@example.test',
            'name' => 'Test Admin',
            'email_verified' => true,
            'provider' => 'google.com',
        ]);

        $this->postJson(route('admin.login.google'), [
            'id_token' => 'verified-firebase-token',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Google sign-in successful.')
            ->assertJsonPath('redirect_url', route('admin.dashboard'));

        $this->assertSame(1, (int) session('admin_id'));
        Log::shouldHaveReceived('warning')
            ->with('Firebase admin security event write failed.', Mockery::on(
                fn (array $context): bool => ($context['failure_type'] ?? null) === 'firebase_audit_write_failed'
                    && ($context['event_type'] ?? null) === 'admin_login_google_success'
                    && ! array_key_exists('error', $context)
            ));
    }

    public function test_google_login_preserves_totp_as_the_second_factor_without_creating_a_web_push_challenge(): void
    {
        DB::table('admins')->where('admin_id', 1)->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => 'encrypted-secret-placeholder',
        ]);

        $this->mockIdentity([
            'uid' => 'google-admin-uid',
            'email' => 'admin@example.test',
            'name' => 'Test Admin',
            'email_verified' => true,
            'provider' => 'google.com',
        ]);

        $this->postJson(route('admin.login.google'), ['id_token' => 'verified-firebase-token'])
            ->assertOk()
            ->assertJsonPath('requires_two_factor', true)
            ->assertJsonPath('redirect_url', route('admin.login'));

        $pending = session('pending_admin_2fa');
        $this->assertIsArray($pending);
        $this->assertArrayNotHasKey('challenge_id', $pending);
        $this->assertArrayNotHasKey('prompt_number', $pending);
        $this->assertArrayNotHasKey('browser_prompt_enabled', $pending);
        $this->assertSame('google', $pending['primary_method']);
        $this->assertNull(session('admin_id'));

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Enter your Google Authenticator code to continue.')
            ->assertSee('Authenticator Code')
            ->assertDontSee('GOOGLE PROMPT-STYLE APPROVAL')
            ->assertDontSee('Send notification again')
            ->assertDontSee('Use Google Authenticator code instead');
    }

    private function mockIdentity(array $identity): void
    {
        $mock = Mockery::mock(FirebaseGoogleIdentityService::class);
        $mock->shouldReceive('verify')
            ->once()
            ->andReturn($identity);

        $this->app->instance(FirebaseGoogleIdentityService::class, $mock);
    }
}
