<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminRememberMeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.secure', false);
        Schema::dropIfExists('admin_security_settings');
        Schema::dropIfExists('admins');

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('username', 100);
            $table->string('email', 150);
            $table->string('password');
            $table->string('full_name', 150);
            $table->string('role', 30)->default('admin');
            $table->boolean('is_active')->default(true);
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
            'password' => Hash::make('correct-password'),
            'full_name' => 'Test Admin',
            'role' => 'admin',
        ]);
    }

    public function test_login_form_has_one_associated_remember_checkbox_inside_the_login_form(): void
    {
        $response = $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('id="adminLoginForm"', false)
            ->assertSee('type="checkbox" id="remember" name="remember" value="1"', false)
            ->assertSee('for="remember"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'id="remember"'));
    }

    public function test_remembered_admin_session_is_restored_after_the_browser_session_is_lost(): void
    {
        $login = $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'correct-password',
            'remember' => '1',
        ]);

        $login->assertRedirect(route('admin.dashboard'))
            ->assertCookie('admin_remember');

        $storedToken = (string) DB::table('admins')->where('admin_id', 1)->value('remember_token');
        $this->assertNotSame('', $storedToken);
        $this->assertNotSame('correct-password', $storedToken);
        $this->assertNotNull(DB::table('admins')->where('admin_id', 1)->value('remember_token_expires_at'));

        $rememberCookieObject = $login->getCookie('admin_remember');
        $this->assertTrue($rememberCookieObject->isHttpOnly());
        $rememberCookie = $rememberCookieObject->getValue();
        [$cookieAdminId, $plainToken] = explode('|', Crypt::decryptString($rememberCookie), 2);
        $this->assertSame('1', $cookieAdminId);
        $this->assertTrue(Hash::check($plainToken, $storedToken));
        $this->withSession(['admin_id' => null, 'admin_role' => null])
            ->withCookie('admin_remember', $rememberCookie)
            ->get('/')
            ->assertRedirect(route('admin.login'));

        $this->withCookie('admin_remember', $rememberCookie)
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_id', 1);
    }

    public function test_unchecked_remember_me_does_not_leave_a_persistent_token(): void
    {
        $login = $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'correct-password',
        ]);

        $login->assertRedirect(route('admin.dashboard'))
            ->assertCookieExpired('admin_remember');

        $this->assertNull(DB::table('admins')->where('admin_id', 1)->value('remember_token'));
        $this->assertNull(DB::table('admins')->where('admin_id', 1)->value('remember_token_expires_at'));
    }

    public function test_remember_cookie_honors_the_configured_secure_cookie_setting(): void
    {
        config()->set('session.secure', true);

        $response = $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'correct-password',
            'remember' => true,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue($response->getCookie('admin_remember')->isSecure());
        $this->assertTrue($response->getCookie('admin_remember')->isHttpOnly());
    }

    public function test_logout_revokes_the_remember_token_and_prevents_reuse_of_the_cookie(): void
    {
        $login = $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'correct-password',
            'remember' => true,
        ]);
        $rememberCookie = $login->getCookie('admin_remember')->getValue();

        $this->withoutMiddleware(EnsureAdminAuthenticated::class)
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'))
            ->assertCookieExpired('admin_remember');

        $this->assertNull(DB::table('admins')->where('admin_id', 1)->value('remember_token'));

        $this->withSession(['admin_id' => null, 'admin_role' => null])
            ->withCookie('admin_remember', $rememberCookie)
            ->get(route('admin.login'))
            ->assertOk()
            ->assertSessionMissing('admin_id');
    }

    public function test_invalid_credentials_do_not_issue_a_remember_token(): void
    {
        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'incorrect-password',
            'remember' => true,
        ])
            ->assertSessionHasErrors('email')
            ->assertCookieMissing('admin_remember');

        $this->assertNull(DB::table('admins')->where('admin_id', 1)->value('remember_token'));
    }

    public function test_remember_is_issued_only_after_required_two_factor_verification_and_then_restores_session(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        DB::table('admins')->where('admin_id', 1)->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString($secret),
        ]);

        $primaryAuth = $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'correct-password',
            'remember' => true,
        ]);

        $primaryAuth->assertRedirect(route('admin.login'))
            ->assertCookieMissing('admin_remember')
            ->assertSessionHas('pending_admin_2fa');
        $this->assertNull(DB::table('admins')->where('admin_id', 1)->value('remember_token'));

        $verified = $this->post(route('admin.2fa.verify'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ]);

        $verified->assertRedirect(route('admin.dashboard'))
            ->assertCookie('admin_remember');
        $rememberCookie = $verified->getCookie('admin_remember')->getValue();
        $this->assertNotNull(DB::table('admins')->where('admin_id', 1)->value('remember_token'));

        $this->withSession(['admin_id' => null, 'admin_role' => null])
            ->withCookie('admin_remember', $rememberCookie)
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_id', 1);
    }

    public function test_inactive_account_cannot_restore_a_remembered_session(): void
    {
        $login = $this->post(route('admin.login.store'), [
            'email' => 'admin@example.test',
            'password' => 'correct-password',
            'remember' => true,
        ]);
        $rememberCookie = $login->getCookie('admin_remember')->getValue();

        DB::table('admins')->where('admin_id', 1)->update(['is_active' => false]);

        $this->withSession(['admin_id' => null, 'admin_role' => null])
            ->withCookie('admin_remember', $rememberCookie)
            ->get(route('admin.login'))
            ->assertOk()
            ->assertSessionMissing('admin_id');

        $this->assertNull(DB::table('admins')->where('admin_id', 1)->value('remember_token'));
        $this->assertNull(DB::table('admins')->where('admin_id', 1)->value('remember_token_expires_at'));
    }
}
