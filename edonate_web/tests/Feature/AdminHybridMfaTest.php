<?php

namespace Tests\Feature;

use App\Services\AdminMfaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminHybridMfaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.webpush.vapid_public_key', '');
        config()->set('services.webpush.vapid_private_key', '');

        Schema::dropIfExists('admin_devices');
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

        Schema::create('admin_devices', function (Blueprint $table): void {
            $table->bigIncrements('admin_device_id');
            $table->integer('user_id')->index();
            $table->text('endpoint');
            $table->string('public_key', 512);
            $table->string('auth_token', 512);
            $table->timestamps();
        });

        DB::table('admins')->insert([
            'admin_id' => 1,
            'username' => 'test-admin',
            'email' => 'admin@example.test',
            'password' => 'not-used-by-this-test',
            'full_name' => 'Test Admin',
            'role' => 'admin',
            'two_factor_enabled' => true,
            'two_factor_secret' => 'test-secret',
        ]);
    }

    public function test_signed_mobile_page_accepts_the_matching_number_once(): void
    {
        $service = app(AdminMfaService::class);
        $challenge = $service->createChallenge(1);
        $expiresAt = now()->addMinutes(5);

        $showUrl = URL::temporarySignedRoute(
            'admin.mfa.mobile',
            $expiresAt,
            ['challenge' => $challenge['id']]
        );

        $this->get($showUrl)
            ->assertOk()
            ->assertSee('Approve admin sign-in')
            ->assertSee('Which number is on your computer?')
            ->assertSee('name="number"', false);

        $approveUrl = URL::temporarySignedRoute(
            'admin.mfa.mobile.approve',
            $expiresAt,
            ['challenge' => $challenge['id']]
        );

        $this->post($approveUrl, ['number' => $challenge['number']])
            ->assertOk()
            ->assertSee('Sign-in approved');

        $this->assertSame('approved', app(AdminMfaService::class)->getChallenge($challenge['id'])['status']);

        // A second submission cannot re-approve or re-broadcast the same
        // one-time challenge.
        $this->post($approveUrl, ['number' => $challenge['number']])
            ->assertOk()
            ->assertSee('Number did not match');
    }

    public function test_prompt_completion_reuses_the_existing_admin_login_session_flow(): void
    {
        $service = app(AdminMfaService::class);
        $challenge = $service->createChallenge(1);
        $this->assertTrue($service->approveChallenge($challenge['id'], 1, $challenge['number']));

        $pending = [
            'admin_id' => 1,
            'email' => 'admin@example.test',
            'role' => 'admin',
            'remember' => false,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5)->timestamp,
            'challenge_id' => $challenge['id'],
            'prompt_number' => $challenge['number'],
            'prompt_expires_at' => $challenge['expires_at'],
            'prompt_available' => true,
        ];

        $this->withSession(['pending_admin_2fa' => $pending])
            ->post(route('admin.2fa.prompt.complete'), ['challenge' => $challenge['id']])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_id', 1)
            ->assertSessionHas('admin_role', 'admin');

        $this->assertDatabaseHas('admins', [
            'admin_id' => 1,
        ]);
        $this->assertNotNull(DB::table('admins')->where('admin_id', 1)->value('two_factor_last_verified_at'));
    }

    public function test_device_registration_uses_the_authenticated_admin_session(): void
    {
        $payload = [
            'endpoint' => 'https://push.example.test/subscription/abc',
            'keys' => [
                'p256dh' => 'public-key',
                'auth' => 'auth-token',
            ],
        ];

        $this->withSession([
            'admin_id' => 1,
            'admin_username' => 'test-admin',
            'admin_full_name' => 'Test Admin',
            'admin_role' => 'admin',
        ])
            ->postJson(route('admin.mfa.devices.store'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('admin_devices', [
            'user_id' => 1,
            'endpoint' => $payload['endpoint'],
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
        ]);
    }

    public function test_login_modal_contains_number_matching_and_totp_fallback(): void
    {
        $html = view('admin._two_factor_challenge_modal', [
            'twoFactorChallengeModal' => [
                'show' => true,
                'maskedEmail' => 'a***@example.test',
                'remainingSeconds' => 300,
                'promptNumber' => '73',
                'promptAvailable' => true,
                'challengeId' => 'challenge123',
                'statusUrl' => route('admin.2fa.prompt.status'),
                'triggerUrl' => route('admin.2fa.prompt.trigger'),
                'completeUrl' => route('admin.2fa.prompt.complete'),
                'channelName' => 'admin-mfa.challenge123',
            ],
        ])->with('errors', new \Illuminate\Support\ViewErrorBag())->render();

        $this->assertStringContainsString('GOOGLE PROMPT-STYLE APPROVAL', $html);
        $this->assertStringContainsString('73', $html);
        $this->assertStringContainsString('Use Google Authenticator code instead', $html);
        $this->assertStringContainsString('name="code"', $html);
    }
}
