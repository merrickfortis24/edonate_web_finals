<?php

namespace Tests\Feature;

use App\Mail\AdminPasswordResetMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            $table->json('two_factor_recovery_codes')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('remember_token_expires_at')->nullable();
            $table->timestamps();
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

        DB::table('admins')->where('email', self::EMAIL)->update([
            'remember_token' => 'old-remember-token',
            'remember_token_expires_at' => now()->addDays(10),
            'two_factor_enabled' => true,
            'two_factor_secret' => 'encrypted-two-factor-secret',
            'two_factor_recovery_codes' => json_encode(['recovery-code-hash']),
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

        $this->get((string) $resetUrl)->assertOk();

        $this->post(route('admin.password.reset'), [
            'email' => self::EMAIL,
            'token' => $query['token'],
            'password' => 'replacement-password-123',
            'password_confirmation' => 'replacement-password-123',
        ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('success', 'Your password has been reset. You can now log in.');

        $admin = DB::table('admins')->where('email', self::EMAIL)->first();
        $this->assertTrue(Hash::check('replacement-password-123', $admin->password));
        $this->assertNull($admin->remember_token);
        $this->assertNull($admin->remember_token_expires_at);
        $this->assertTrue((bool) $admin->two_factor_enabled);
        $this->assertSame('encrypted-two-factor-secret', $admin->two_factor_secret);
        $this->assertSame(['recovery-code-hash'], json_decode($admin->two_factor_recovery_codes, true));
        $this->assertNotNull(DB::table('admin_trusted_devices')->value('revoked_at'));
        $this->assertNull(Cache::store('file')->get(self::CACHE_KEY));
    }
}
