<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminHybridMfaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('admins');

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('username', 100);
            $table->string('email', 150);
            $table->string('password');
            $table->string('full_name', 150);
            $table->string('role', 30)->default('admin');
            $table->boolean('two_factor_enabled')->default(true);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->json('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_last_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('remember_token_expires_at')->nullable();
        });

        DB::table('admins')->insert([
            'admin_id' => 1,
            'username' => 'test-admin',
            'email' => 'admin@example.test',
            'password' => 'not-used-by-this-test',
            'full_name' => 'Test Admin',
            'role' => 'admin',
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        ]);
    }

    public function test_login_modal_contains_authenticator_but_not_recovery_code_controls(): void
    {
        $html = view('admin._two_factor_challenge_modal', [
            'twoFactorChallengeModal' => [
                'show' => true,
                'maskedEmail' => 'a***@example.test',
                'remainingSeconds' => 300,
                'trustedDevicesAvailable' => true,
            ],
        ])->with('errors', new ViewErrorBag())->render();

        $this->assertStringContainsString('Two-Factor Verification', $html);
        $this->assertStringContainsString('Enter your Google Authenticator code to continue.', $html);
        $this->assertStringContainsString('name="code"', $html);
        $this->assertStringNotContainsString('name="recovery_code"', $html);
        $this->assertStringNotContainsString('Recovery Code', $html);
        $this->assertStringContainsString('name="trust_device"', $html);
        $this->assertStringContainsString('Verify and Continue', $html);
        $this->assertStringContainsString('Back', $html);

        $this->assertStringNotContainsString('GOOGLE PROMPT-STYLE APPROVAL', $html);
        $this->assertStringNotContainsString('Confirm this sign-in from your phone', $html);
        $this->assertStringNotContainsString('Send notification again', $html);
        $this->assertStringNotContainsString('Use Google Authenticator code instead', $html);
    }

    public function test_google_authenticator_code_still_completes_a_pending_login(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $code = (new Google2FA)->getCurrentOtp($secret);

        $response = $this->withSession([
            'pending_admin_2fa' => [
                'admin_id' => 1,
                'email' => 'admin@example.test',
                'role' => 'admin',
                'remember' => false,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
                'primary_method' => 'password',
            ],
        ])->post(route('admin.2fa.verify'), ['code' => $code]);

        $response
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_id', 1)
            ->assertSessionHas('admin_role', 'admin');

        $this->assertNotNull(DB::table('admins')->where('admin_id', 1)->value('two_factor_last_verified_at'));
    }

    public function test_recovery_code_cannot_be_used_to_bypass_the_authenticator_login_challenge(): void
    {
        $recoveryCode = 'HSC7H-GJNQS';

        DB::table('admins')->where('admin_id', 1)->update([
            'two_factor_recovery_codes' => json_encode([Hash::make($recoveryCode)]),
        ]);

        $response = $this->withSession([
            'pending_admin_2fa' => [
                'admin_id' => 1,
                'email' => 'admin@example.test',
                'role' => 'admin',
                'remember' => false,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
                'primary_method' => 'password',
            ],
        ])->post(route('admin.2fa.verify'), ['recovery_code' => $recoveryCode]);

        $response
            ->assertSessionHasErrors('code')
            ->assertSessionMissing('admin_id');

        $remainingCodes = json_decode(
            (string) DB::table('admins')->where('admin_id', 1)->value('two_factor_recovery_codes'),
            true
        );

        $this->assertCount(1, $remainingCodes);
        $this->assertTrue(Hash::check($recoveryCode, $remainingCodes[0]));
    }

    public function test_enabling_two_factor_shows_codes_once_and_requires_acknowledgement(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        DB::table('admins')->where('admin_id', 1)->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ]);

        $this->withSession([
            'admin_id' => 1,
            'admin_role' => 'admin',
            'admin_2fa_setup_secret' => $secret,
        ])->post(route('admin.2fa.enable'), [
            'otp' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertRedirect(route('admin.2fa.recovery-codes.show'));

        $codesPage = $this->get(route('admin.2fa.recovery-codes.show'))
            ->assertOk()
            ->assertSee('Save these recovery codes now. You will need one if you reset your password, and they will not be shown again.')
            ->assertSee('Copy Codes')
            ->assertSee('Download Codes')
            ->assertSee('name="saved_codes"', false);
        $this->assertStringContainsString('no-store', (string) $codesPage->headers->get('Cache-Control'));

        $storedHashes = json_decode((string) DB::table('admins')->where('admin_id', 1)->value('two_factor_recovery_codes'), true);
        $this->assertCount(8, $storedHashes);
        $this->assertStringNotContainsString(json_encode($storedHashes), $codesPage->getContent());

        $this->post(route('admin.2fa.recovery-codes.acknowledge'), [])
            ->assertSessionHasErrors('saved_codes');

        $this->get(route('admin.2fa.recovery-codes.show'))->assertSee('Copy Codes');

        $this->post(route('admin.2fa.recovery-codes.acknowledge'), ['saved_codes' => '1'])
            ->assertRedirect(route('admin.2fa.setup'))
            ->assertSessionMissing('admin_recovery_codes_once');

        $this->get(route('admin.2fa.recovery-codes.show'))
            ->assertRedirect(route('admin.2fa.setup'));
    }

    public function test_regenerating_recovery_codes_verifies_totp_and_replaces_the_old_hashes(): void
    {
        $oldCode = 'HSC7G-JNQSA';
        DB::table('admins')->where('admin_id', 1)->update([
            'two_factor_recovery_codes' => json_encode([Hash::make($oldCode)]),
        ]);

        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->post(route('admin.2fa.recovery-codes.regenerate'), [
                'otp' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP'),
            ])
            ->assertRedirect(route('admin.2fa.recovery-codes.show'));

        $storedHashes = json_decode((string) DB::table('admins')->where('admin_id', 1)->value('two_factor_recovery_codes'), true);
        $this->assertCount(8, $storedHashes);
        foreach ($storedHashes as $hash) {
            $this->assertFalse(Hash::check($oldCode, $hash));
        }

        $this->get(route('admin.2fa.recovery-codes.show'))
            ->assertOk()
            ->assertSee('Copy Codes')
            ->assertSee('Download Codes');
    }

    public function test_prompt_and_device_routes_are_not_registered(): void
    {
        foreach ([
            'admin.2fa.prompt.trigger',
            'admin.2fa.prompt.status',
            'admin.2fa.prompt.complete',
            'admin.mfa.mobile',
            'admin.mfa.mobile.approve',
            'admin.mfa.devices.store',
            'admin.mfa.devices.destroy',
        ] as $routeName) {
            $this->assertNull(
                app('router')->getRoutes()->getByName($routeName),
                "Removed prompt route [{$routeName}] should not exist."
            );
        }

        foreach ([
            'admin.2fa.verify',
            'admin.2fa.cancel',
            'admin.2fa.setup',
            'admin.2fa.enable',
            'admin.2fa.disable',
        ] as $routeName) {
            $this->assertNotNull(
                app('router')->getRoutes()->getByName($routeName),
                "Google Authenticator route [{$routeName}] should exist."
            );
        }
    }
}
