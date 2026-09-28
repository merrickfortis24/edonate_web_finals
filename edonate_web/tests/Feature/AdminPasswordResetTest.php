<?php

namespace Tests\Feature;

use App\Mail\AdminPasswordResetMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    private const EMAIL = 'admin@example.test';

    private const GENERIC_RESPONSE = 'If an account exists for that email address, your reset request has been received. Check your inbox and spam folder, and contact the administrator if no instructions arrive.';

    private const CACHE_KEY = 'admin_password_reset:admin@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::store('file')->forget(self::CACHE_KEY);

        Schema::dropIfExists('admin_trusted_devices');
        Schema::dropIfExists('admins');
        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('username', 100)->unique();
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->string('full_name', 150)->nullable();
            $table->string('role', 30)->default('admin');
            $table->boolean('is_active')->default(true);
            $table->boolean('two_factor_enabled')->default(true);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->json('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_last_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('remember_token_expires_at')->nullable();
            $table->unsignedInteger('auth_version')->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('audit_logs');
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('audit_log_id');
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_role', 50)->nullable();
            $table->string('action_type', 50);
            $table->string('module_type', 80)->nullable();
            $table->string('target_table', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('description', 255);
            $table->string('ip_address', 45)->nullable();
            $table->string('result', 30)->default('success');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('admins')->insert([
            'username' => 'test-admin',
            'email' => self::EMAIL,
            'password' => Hash::make('current-password'),
            'full_name' => 'Test Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $trustedDeviceMigration = require database_path('migrations/2026_09_28_000001_create_admin_trusted_devices_table.php');
        $trustedDeviceMigration->up();

    }

    protected function tearDown(): void
    {
        Cache::store('file')->forget(self::CACHE_KEY);

        parent::tearDown();
    }

    public function test_forgot_password_page_posts_the_email_with_csrf_protection(): void
    {
        $this->get(route('admin.password.request'))
            ->assertOk()
            ->assertSee('method="POST" action="'.route('admin.password.email').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('type="email"', false)
            ->assertSee('name="email"', false);
    }

    public function test_valid_admin_request_sends_the_reset_mailable_and_stores_only_a_token_hash(): void
    {
        Mail::fake();

        $this->from(route('admin.password.request'))
            ->post(route('admin.password.email'), ['email' => self::EMAIL])
            ->assertRedirect(route('admin.password.request'))
            ->assertSessionHas('success', self::GENERIC_RESPONSE);

        $cachedReset = Cache::store('file')->get(self::CACHE_KEY);
        $this->assertIsArray($cachedReset);
        $this->assertArrayHasKey('token_hash', $cachedReset);
        $this->assertNotSame('', $cachedReset['token_hash']);

        $resetUrl = null;
        Mail::assertSent(AdminPasswordResetMail::class, function (AdminPasswordResetMail $mail) use (&$resetUrl): bool {
            $resetUrl = $mail->resetUrl;

            return $mail->hasTo(self::EMAIL)
                && $mail->expiryMinutes === 30;
        });

        $this->assertIsString($resetUrl);
        $this->assertSame('/admin/reset-password', parse_url($resetUrl, PHP_URL_PATH));
        parse_str((string) parse_url($resetUrl, PHP_URL_QUERY), $query);
        $this->assertSame(self::EMAIL, $query['email'] ?? null);
        $this->assertSame(64, strlen((string) ($query['token'] ?? '')));
        $this->assertTrue(Hash::check($query['token'], $cachedReset['token_hash']));
    }

    public function test_unknown_email_gets_the_same_generic_response_without_sending_mail(): void
    {
        Mail::fake();

        $this->post(route('admin.password.email'), ['email' => 'unknown@example.test'])
            ->assertRedirect()
            ->assertSessionHas('success', self::GENERIC_RESPONSE);

        Mail::assertNothingSent();
    }

    public function test_mail_failure_cleans_up_its_token_and_does_not_disclose_account_existence(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with(self::EMAIL)
            ->andThrow(new RuntimeException('SMTP rejected recipient admin@example.test'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to send admin password reset email.', Mockery::on(function (array $context): bool {
                return ($context['admin_id'] ?? null) === 1
                    && ($context['exception_class'] ?? null) === RuntimeException::class
                    && ! array_key_exists('email', $context)
                    && ! str_contains((string) ($context['message'] ?? ''), self::EMAIL);
            }));

        $this->post(route('admin.password.email'), ['email' => self::EMAIL])
            ->assertRedirect()
            ->assertSessionHas('success', self::GENERIC_RESPONSE);

        $this->assertNull(Cache::store('file')->get(self::CACHE_KEY));
    }

    public function test_reset_link_completes_password_change_and_invalidates_remember_me_storage(): void
    {
        Mail::fake();

        $usedCode = 'HSC7G-JNQSA';
        $unusedCode = 'JQW38-ACD5P';
        DB::table('admins')->where('email', self::EMAIL)->update([
            'remember_token' => 'old-remember-token',
            'remember_token_expires_at' => now()->addDays(10),
            'two_factor_enabled' => true,
            'two_factor_secret' => 'encrypted-two-factor-secret',
            'two_factor_recovery_codes' => json_encode([Hash::make($usedCode), Hash::make($unusedCode)]),
        ]);
        DB::table('admin_trusted_devices')->insert([
            'admin_id' => 1,
            'token_hash' => hash('sha256', 'existing-trusted-device-token'),
            'device_name' => 'Test browser',
            'trusted_at' => now(),
            'expires_at' => now()->addDays(20),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(route('admin.password.email'), ['email' => self::EMAIL]);

        $resetUrl = null;
        Mail::assertSent(AdminPasswordResetMail::class, function (AdminPasswordResetMail $mail) use (&$resetUrl): bool {
            $resetUrl = $mail->resetUrl;

            return true;
        });
        parse_str((string) parse_url((string) $resetUrl, PHP_URL_QUERY), $query);

        $this->get((string) $resetUrl)
            ->assertOk()
            ->assertSee('name="recovery_code"', false);

        $this->post(route('admin.password.reset'), [
            'email' => self::EMAIL,
            'token' => $query['token'],
            'recovery_code' => $usedCode,
            'password' => 'replacement-password-123',
            'password_confirmation' => 'replacement-password-123',
        ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('success', 'Your password has been reset. You can now log in.');

        $admin = DB::table('admins')->where('email', self::EMAIL)->first();
        $this->assertTrue(Hash::check('replacement-password-123', $admin->password));
        $this->assertNull($admin->remember_token);
        $this->assertNull($admin->remember_token_expires_at);
        $this->assertSame(1, (int) $admin->auth_version);
        $this->assertTrue((bool) $admin->two_factor_enabled);
        $this->assertSame('encrypted-two-factor-secret', $admin->two_factor_secret);
        $remainingHashes = json_decode($admin->two_factor_recovery_codes, true);
        $this->assertCount(1, $remainingHashes);
        $this->assertTrue(Hash::check($unusedCode, $remainingHashes[0]));
        $this->assertNotNull(DB::table('admin_trusted_devices')->value('revoked_at'));
        $this->assertNull(Cache::store('file')->get(self::CACHE_KEY));
        $this->assertFalse(session()->has('admin_id'));
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => 'admin_password_reset_success',
            'target_id' => 1,
            'result' => 'success',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => 'admin_password_reset_recovery_attempt',
            'target_id' => 1,
            'result' => 'success',
        ]);

        Route::middleware(['web', 'admin.auth'])->get('/__test/admin-session-version', static fn () => response('ok'));
        $this->withSession([
            'admin_id' => 1,
            'admin_role' => 'admin',
            'admin_auth_version' => 0,
        ])->get('/__test/admin-session-version')
            ->assertRedirect(route('admin.login'))
            ->assertSessionMissing('admin_id');
    }

    public function test_invalid_or_reused_recovery_code_does_not_change_the_password_or_consume_another_code(): void
    {
        config(['edonate.rate_limits.password_reset_per_ten_minutes' => 10]);
        Mail::fake();
        $availableCode = 'HSC7G-JNQSA';
        DB::table('admins')->where('email', self::EMAIL)->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => 'encrypted-two-factor-secret',
            'two_factor_recovery_codes' => json_encode([Hash::make($availableCode)]),
        ]);

        $this->post(route('admin.password.email'), ['email' => self::EMAIL]);
        $resetUrl = null;
        Mail::assertSent(AdminPasswordResetMail::class, function (AdminPasswordResetMail $mail) use (&$resetUrl): bool {
            $resetUrl = $mail->resetUrl;

            return true;
        });
        parse_str((string) parse_url((string) $resetUrl, PHP_URL_QUERY), $query);

        $this->from((string) $resetUrl)->post(route('admin.password.reset'), [
            'email' => self::EMAIL,
            'token' => $query['token'],
            'recovery_code' => 'NOT-A-VALID-CODE',
            'password' => 'replacement-password-123',
            'password_confirmation' => 'replacement-password-123',
        ])->assertRedirect(route('admin.password.request'))
            ->assertSessionHasErrors('reset');

        $admin = DB::table('admins')->where('email', self::EMAIL)->first();
        $this->assertTrue(Hash::check('current-password', $admin->password));
        $storedHashes = json_decode($admin->two_factor_recovery_codes, true);
        $this->assertCount(1, $storedHashes);
        $this->assertTrue(Hash::check($availableCode, $storedHashes[0]));
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => 'admin_password_reset_recovery_attempt',
            'target_id' => 1,
            'result' => 'failed',
        ]);

        $this->post(route('admin.password.reset'), [
            'email' => self::EMAIL,
            'token' => $query['token'],
            'recovery_code' => $availableCode,
            'password' => 'replacement-password-123',
            'password_confirmation' => 'replacement-password-123',
        ])->assertRedirect(route('admin.login'));

        $this->post(route('admin.password.email'), ['email' => self::EMAIL]);
        $newResetUrl = null;
        Mail::assertSent(AdminPasswordResetMail::class, function (AdminPasswordResetMail $mail) use (&$newResetUrl): bool {
            $newResetUrl = $mail->resetUrl;

            return true;
        });
        parse_str((string) parse_url((string) $newResetUrl, PHP_URL_QUERY), $newQuery);

        $this->post(route('admin.password.reset'), [
            'email' => self::EMAIL,
            'token' => $newQuery['token'],
            'recovery_code' => $availableCode,
            'password' => 'another-replacement-password',
            'password_confirmation' => 'another-replacement-password',
        ])->assertRedirect(route('admin.password.request'))
            ->assertSessionHasErrors('reset');

        $admin = DB::table('admins')->where('email', self::EMAIL)->first();
        $this->assertTrue(Hash::check('replacement-password-123', $admin->password));
        $this->assertSame([], json_decode($admin->two_factor_recovery_codes, true));
    }

    public function test_expired_reset_link_is_rejected(): void
    {
        $token = str_repeat('a', 64);
        Cache::store('file')->put(self::CACHE_KEY, ['token_hash' => Hash::make($token)], now()->subMinute());

        $this->get(route('admin.password.reset.form', ['email' => self::EMAIL, 'token' => $token]))
            ->assertRedirect(route('admin.password.request'))
            ->assertSessionHasErrors('email');

        $this->post(route('admin.password.reset'), [
            'email' => self::EMAIL,
            'token' => $token,
            'recovery_code' => 'HSC7G-JNQSA',
            'password' => 'replacement-password-123',
            'password_confirmation' => 'replacement-password-123',
        ])->assertRedirect(route('admin.password.request'))
            ->assertSessionHasErrors('reset');

        $this->assertTrue(Hash::check('current-password', DB::table('admins')->where('email', self::EMAIL)->value('password')));
    }
}
