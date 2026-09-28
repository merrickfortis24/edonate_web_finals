<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminTrustedDeviceTest extends TestCase
{
    private const SECRET = 'JBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.secure', false);
        Schema::dropIfExists('admin_trusted_devices');
        Schema::dropIfExists('admin_security_settings');
        Schema::dropIfExists('admins');

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('username', 100)->unique();
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->string('full_name', 150);
            $table->string('role', 30)->default('admin');
            $table->boolean('is_active')->default(true);
            $table->boolean('two_factor_enabled')->default(true);
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

        $trustedDeviceMigration = require database_path('migrations/2026_09_28_000001_create_admin_trusted_devices_table.php');
        $trustedDeviceMigration->up();

        $this->insertAdmin(1, 'admin@example.test');
        $this->insertAdmin(2, 'second-admin@example.test');
    }

    public function test_trusted_checkbox_is_offered_but_does_not_trust_a_device_before_valid_2fa(): void
    {
        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.login'))->assertSessionHas('pending_admin_2fa');

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('name="trust_device"', false)
            ->assertSee('Don’t ask again for 30 days');

        $this->post(route('admin.2fa.verify'), [
            'code' => $this->invalidOtp(),
            'trust_device' => '1',
        ])->assertSessionHasErrors('code');

        $this->assertSame(0, DB::table('admin_trusted_devices')->count());
    }

    public function test_successful_2fa_can_create_a_hashed_trusted_device_for_exactly_30_days(): void
    {
        $this->loginToChallenge();

        $response = $this->post(route('admin.2fa.verify'), [
            'code' => $this->validOtp(),
            'trust_device' => '1',
        ]);

        $response->assertRedirect(route('admin.dashboard'))
            ->assertCookie('edonate_admin_trusted_device');

        $device = DB::table('admin_trusted_devices')->first();
        $this->assertNotNull($device);
        $this->assertSame(1, (int) $device->admin_id);
        $this->assertSame(64, strlen((string) $device->token_hash));
        $cookieValue = $response->getCookie('edonate_admin_trusted_device')->getValue();
        $this->assertNotSame($cookieValue, $device->token_hash);
        $plainCookieToken = Crypt::decryptString($cookieValue);
        $this->assertNotSame($plainCookieToken, $device->token_hash);
        $this->assertSame(hash('sha256', $plainCookieToken), $device->token_hash);
        $this->assertSame(
            strtotime((string) $device->trusted_at) + (30 * 24 * 60 * 60),
            strtotime((string) $device->expires_at)
        );
        $this->assertTrue($response->getCookie('edonate_admin_trusted_device')->isHttpOnly());
        $this->assertSame('lax', $response->getCookie('edonate_admin_trusted_device')->getSameSite());
    }

    public function test_unchecked_trust_option_does_not_create_a_persistent_device(): void
    {
        $this->loginToChallenge();

        $response = $this->post(route('admin.2fa.verify'), ['code' => $this->validOtp()]);

        $response->assertRedirect(route('admin.dashboard'))
            ->assertCookieMissing('edonate_admin_trusted_device');
        $this->assertSame(0, DB::table('admin_trusted_devices')->count());
    }

    public function test_remember_me_and_trusted_device_are_issued_as_separate_credentials(): void
    {
        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'correct-password',
            'remember' => '1',
        ])->assertRedirect(route('admin.login'))->assertSessionHas('pending_admin_2fa');

        $response = $this->post(route('admin.2fa.verify'), [
            'code' => $this->validOtp(),
            'trust_device' => '1',
        ]);

        $response->assertRedirect(route('admin.dashboard'))
            ->assertCookie('admin_remember')
            ->assertCookie('edonate_admin_trusted_device');

        $this->assertNotSame(
            $response->getCookie('admin_remember')->getValue(),
            $response->getCookie('edonate_admin_trusted_device')->getValue()
        );
        $this->assertNotNull(DB::table('admins')->where('admin_id', 1)->value('remember_token'));
        $this->assertSame(1, DB::table('admin_trusted_devices')->where('admin_id', 1)->count());
    }

    public function test_trusted_device_cookie_uses_the_configured_secure_flag(): void
    {
        config()->set('session.secure', true);
        $verified = $this->completeChallengeAndTrust();

        $this->assertTrue($verified->getCookie('edonate_admin_trusted_device')->isSecure());
        $this->assertTrue($verified->getCookie('edonate_admin_trusted_device')->isHttpOnly());
    }

    public function test_valid_trusted_cookie_skips_2fa_for_the_same_admin_and_rotates_without_extending_expiry(): void
    {
        $verified = $this->completeChallengeAndTrust();
        $cookie = $verified->getCookie('edonate_admin_trusted_device')->getValue();
        $original = DB::table('admin_trusted_devices')->first();

        $this->travel(10)->minutes();
        $this->withSession([])
            ->withCookie('edonate_admin_trusted_device', $cookie)
            ->post(route('admin.login.store'), [
                'email' => 'admin@example.test',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_id', 1)
            ->assertSessionMissing('pending_admin_2fa')
            ->assertCookie('edonate_admin_trusted_device');

        $rotated = DB::table('admin_trusted_devices')->first();
        $this->assertNotSame($original->token_hash, $rotated->token_hash);
        $this->assertSame($original->expires_at, $rotated->expires_at);
        $this->assertGreaterThan(strtotime((string) $original->last_used_at), strtotime((string) $rotated->last_used_at));
    }

    public function test_expired_trusted_cookie_requires_2fa_again(): void
    {
        $verified = $this->completeChallengeAndTrust();
        $cookie = $verified->getCookie('edonate_admin_trusted_device')->getValue();
        DB::table('admin_trusted_devices')->update(['expires_at' => now()->subSecond()]);

        $this->withSession([])
            ->withCookie('edonate_admin_trusted_device', $cookie)
            ->post(route('admin.login.store'), [
                'email' => 'admin@example.test',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('pending_admin_2fa');
    }

    public function test_tampered_or_other_admin_trusted_cookie_never_skips_2fa(): void
    {
        $verified = $this->completeChallengeAndTrust();
        $validCookie = $verified->getCookie('edonate_admin_trusted_device')->getValue();

        $this->withSession([])
            ->withCookie('edonate_admin_trusted_device', Str::random(64))
            ->post(route('admin.login.store'), [
                'email' => 'admin@example.test',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('pending_admin_2fa');

        $this->withSession([])
            ->withCookie('edonate_admin_trusted_device', $validCookie)
            ->post(route('admin.login.store'), [
                'email' => 'second-admin@example.test',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('pending_admin_2fa', fn (array $pending): bool => (int) $pending['admin_id'] === 2);
    }

    public function test_individually_revoked_device_requires_2fa_again(): void
    {
        $verified = $this->completeChallengeAndTrust();
        $cookie = $verified->getCookie('edonate_admin_trusted_device')->getValue();
        $deviceId = (int) DB::table('admin_trusted_devices')->value('id');

        $this->withoutMiddleware(EnsureAdminAuthenticated::class)
            ->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->post(route('admin.2fa.trusted-devices.revoke', $deviceId))
            ->assertRedirect(route('admin.2fa.setup'));

        $this->assertNotNull(DB::table('admin_trusted_devices')->where('id', $deviceId)->value('revoked_at'));
        $this->withSession([])
            ->withCookie('edonate_admin_trusted_device', $cookie)
            ->post(route('admin.login.store'), [
                'email' => 'admin@example.test',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('pending_admin_2fa');
    }

    public function test_manual_revoke_all_marks_records_and_clears_the_current_device_cookie(): void
    {
        $verified = $this->completeChallengeAndTrust();
        $deviceId = (int) DB::table('admin_trusted_devices')->value('id');

        $this->withoutMiddleware(EnsureAdminAuthenticated::class)
            ->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->post(route('admin.2fa.trusted-devices.revoke-all'))
            ->assertRedirect(route('admin.2fa.setup'))
            ->assertCookieExpired('edonate_admin_trusted_device');

        $this->assertNotNull(DB::table('admin_trusted_devices')->where('id', $deviceId)->value('revoked_at'));
    }

    public function test_normal_logout_keeps_the_trusted_device_valid(): void
    {
        $verified = $this->completeChallengeAndTrust();
        $cookie = $verified->getCookie('edonate_admin_trusted_device')->getValue();
        $deviceId = (int) DB::table('admin_trusted_devices')->value('id');

        $this->withoutMiddleware(EnsureAdminAuthenticated::class)
            ->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->withCookie('edonate_admin_trusted_device', $cookie)
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertNull(DB::table('admin_trusted_devices')->where('id', $deviceId)->value('revoked_at'));
        $this->withSession([])
            ->withCookie('edonate_admin_trusted_device', $cookie)
            ->post(route('admin.login.store'), [
                'email' => 'admin@example.test',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_id', 1);
    }

    public function test_disabling_two_factor_revokes_trusted_devices(): void
    {
        $this->completeChallengeAndTrust();
        $deviceId = (int) DB::table('admin_trusted_devices')->value('id');

        $this->withoutMiddleware(EnsureAdminAuthenticated::class)
            ->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->post(route('admin.2fa.disable'), [
                'current_password' => 'correct-password',
                'otp' => $this->validOtp(),
            ])
            ->assertRedirect(route('admin.2fa.setup'));

        $this->assertFalse((bool) DB::table('admins')->where('admin_id', 1)->value('two_factor_enabled'));
        $this->assertNotNull(DB::table('admin_trusted_devices')->where('id', $deviceId)->value('revoked_at'));
    }

    public function test_password_change_revokes_trusted_devices(): void
    {
        $this->completeChallengeAndTrust();
        $deviceId = (int) DB::table('admin_trusted_devices')->value('id');

        $this->withoutMiddleware(EnsureAdminAuthenticated::class)
            ->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->postJson(route('admin.settings.account.update'), [
                'current_password' => 'correct-password',
                'new_password' => 'replacement-password',
                'new_password_confirmation' => 'replacement-password',
            ])
            ->assertOk();

        $this->assertNotNull(DB::table('admin_trusted_devices')->where('id', $deviceId)->value('revoked_at'));
    }

    public function test_security_page_lists_device_metadata_but_never_the_token_hash(): void
    {
        $this->completeChallengeAndTrust();
        $device = DB::table('admin_trusted_devices')->first();

        $response = $this->withoutMiddleware(EnsureAdminAuthenticated::class)
            ->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->get(route('admin.2fa.setup'))
            ->assertOk()
            ->assertSee('Trusted Devices')
            ->assertSee('Active');

        $this->assertStringNotContainsString((string) $device->token_hash, $response->getContent());
    }

    public function test_admin_cannot_revoke_another_admins_device(): void
    {
        $deviceId = $this->insertTrustedDevice(2, 'admin-two-secret-token');

        $this->withoutMiddleware(EnsureAdminAuthenticated::class)
            ->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->post(route('admin.2fa.trusted-devices.revoke', $deviceId))
            ->assertNotFound();

        $this->assertNull(DB::table('admin_trusted_devices')->where('id', $deviceId)->value('revoked_at'));
    }

    public function test_reenabling_or_regenerating_2fa_does_not_restore_old_trust(): void
    {
        $deviceId = $this->insertTrustedDevice(1, 'old-trusted-device-token');

        $this->withoutMiddleware(EnsureAdminAuthenticated::class)
            ->withSession([
                'admin_id' => 1,
                'admin_role' => 'admin',
                'admin_2fa_setup_secret' => self::SECRET,
            ])
            ->post(route('admin.2fa.enable'), ['otp' => $this->validOtp()])
            ->assertRedirect(route('admin.2fa.setup'));

        $this->assertNotNull(DB::table('admin_trusted_devices')->where('id', $deviceId)->value('revoked_at'));
    }

    private function insertAdmin(int $id, string $email): void
    {
        DB::table('admins')->insert([
            'admin_id' => $id,
            'username' => 'admin-'.$id,
            'email' => $email,
            'password' => Hash::make('correct-password'),
            'full_name' => 'Test Admin '.$id,
            'role' => 'admin',
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString(self::SECRET),
        ]);
    }

    private function insertTrustedDevice(int $adminId, string $token): int
    {
        return (int) DB::table('admin_trusted_devices')->insertGetId([
            'admin_id' => $adminId,
            'token_hash' => hash('sha256', $token),
            'device_name' => 'Test device',
            'browser' => 'Test browser',
            'platform' => 'Test OS',
            'ip_address' => '192.0.2.10',
            'trusted_at' => now(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function loginToChallenge(): void
    {
        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'correct-password',
        ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('pending_admin_2fa');
    }

    private function completeChallengeAndTrust()
    {
        $this->loginToChallenge();

        return $this->post(route('admin.2fa.verify'), [
            'code' => $this->validOtp(),
            'trust_device' => '1',
        ])->assertRedirect(route('admin.dashboard'));
    }

    private function validOtp(): string
    {
        return (new Google2FA)->getCurrentOtp(self::SECRET);
    }

    private function invalidOtp(): string
    {
        $google2fa = new Google2FA;

        do {
            $candidate = sprintf('%06d', random_int(0, 999999));
        } while ($google2fa->verifyKey(self::SECRET, $candidate, 1));

        return $candidate;
    }
}
