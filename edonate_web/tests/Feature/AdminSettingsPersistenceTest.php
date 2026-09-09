<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminSettingsPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('admins');

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('full_name')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->bigIncrements('system_setting_id');
            $table->string('setting_key', 100)->unique();
            $table->text('setting_value')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_general_settings_are_saved_and_loaded_from_storage(): void
    {
        $adminId = $this->createAdmin();
        $this->authenticateAsAdmin($adminId);

        $this->postJson('/admin/settings/general', [
            'system_name' => 'Demo eDonate Portal',
            'system_email' => 'settings@example.test',
            'contact_number' => '+63 917 555 0101',
        ])->assertOk()
            ->assertJsonPath('general.systemName', 'Demo eDonate Portal')
            ->assertJsonPath('general.systemEmail', 'settings@example.test');

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'system_name',
            'setting_value' => 'Demo eDonate Portal',
        ]);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('Demo eDonate Portal')
            ->assertSee('settings@example.test');
    }

    public function test_account_settings_verify_and_hash_the_current_admin_password(): void
    {
        $adminId = $this->createAdmin('old-password');
        $this->authenticateAsAdmin($adminId);

        $this->postJson('/admin/settings/account', [
            'current_password' => 'old-password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ])->assertOk()
            ->assertJsonPath('message', 'Password updated successfully.');

        $storedPassword = (string) DB::table('admins')->where('admin_id', $adminId)->value('password');
        $this->assertTrue(Hash::check('new-password-123', $storedPassword));
        $this->assertFalse(Hash::check('old-password', $storedPassword));
    }

    public function test_privacy_and_legal_settings_are_saved_and_published_safely(): void
    {
        $adminId = $this->createAdmin();
        $this->authenticateAsAdmin($adminId);

        $privacyPolicy = 'This custom privacy policy explains collection, use, retention, and user rights. '
            .'<script>alert("unsafe")</script>';
        $terms = 'These custom terms and conditions explain account eligibility, acceptable use, and service limitations.';
        $cookiePolicy = 'This custom cookie policy explains essential storage, optional services, consent, and withdrawal choices.';

        $this->postJson('/admin/settings/privacy-legal', [
            'privacy_policy' => $privacyPolicy,
            'terms_and_conditions' => $terms,
            'cookie_policy' => $cookiePolicy,
            'enforce_cookie_consent_banner' => false,
        ])->assertOk()
            ->assertJsonPath('privacyLegal.privacyPolicy', $privacyPolicy)
            ->assertJsonPath('privacyLegal.termsAndConditions', $terms)
            ->assertJsonPath('privacyLegal.cookiePolicy', $cookiePolicy)
            ->assertJsonPath('privacyLegal.enforceCookieConsentBanner', false);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'privacy_policy',
            'setting_value' => $privacyPolicy,
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'enforce_cookie_consent_banner',
            'setting_value' => '0',
        ]);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('Privacy &amp; Legal', false)
            ->assertSee('for="settingsPrivacyPolicyInput"', false)
            ->assertSee('for="settingsTermsConditionsInput"', false)
            ->assertSee('for="settingsCookiePolicyInput"', false)
            ->assertSee('for="settingsCookieConsentBannerToggle"', false);

        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Published Privacy Policy')
            ->assertSee($privacyPolicy)
            ->assertDontSee('<script>alert("unsafe")</script>', false)
            ->assertSee('data-enforce-banner="false"', false);

        $this->get('/terms-of-service')
            ->assertOk()
            ->assertSee('Published Terms and Conditions')
            ->assertSee($terms);

        $this->get('/cookie-policy')
            ->assertOk()
            ->assertSee('Published Cookie Policy')
            ->assertSee($cookiePolicy);
    }

    public function test_privacy_and_legal_settings_reject_short_policies_and_missing_banner_choice(): void
    {
        $adminId = $this->createAdmin();
        $this->authenticateAsAdmin($adminId);

        $this->postJson('/admin/settings/privacy-legal', [
            'privacy_policy' => 'Too short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'privacy_policy',
                'enforce_cookie_consent_banner',
            ]);
    }

    private function createAdmin(string $password = 'password-123'): int
    {
        return (int) DB::table('admins')->insertGetId([
            'full_name' => 'Settings Admin',
            'username' => 'settings-admin',
            'email' => 'settings-admin@example.test',
            'password' => Hash::make($password),
            'role' => 'admin',
            'created_at' => now(),
        ]);
    }

    private function authenticateAsAdmin(int $adminId): void
    {
        $this->withoutMiddleware([
            EnsureAdminAuthenticated::class,
            EnsureAdminRole::class,
        ]);

        $this->withSession([
            'admin_id' => $adminId,
            'admin_role' => 'admin',
            'admin_username' => 'settings-admin',
            'admin_full_name' => 'Settings Admin',
        ]);
    }
}
