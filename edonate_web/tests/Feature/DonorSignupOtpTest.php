<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DonorSignupOtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('blood_types', function (Blueprint $table): void {
            $table->increments('blood_type_id');
            $table->string('blood_type', 5)->unique();
        });
        DB::table('blood_types')->insert(['blood_type' => 'O+']);

        Schema::create('donor_authentication', function (Blueprint $table): void {
            $table->increments('auth_id');
            $table->unsignedInteger('donor_id');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('verification_sent_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        (require database_path('migrations/2026_09_08_000000_create_privacy_receipts_table.php'))->up();
        Mail::fake();
    }

    public function test_registration_stays_pending_until_a_valid_email_otp_is_confirmed(): void
    {
        $this->postJson(route('donor.signup.send-otp'), [
            'first_name' => 'Mika',
            'last_name' => 'Santos',
            'email' => 'mika.signup@gmail.com',
            'phone' => '09171234567',
            'birthdate' => now()->subYears(25)->toDateString(),
            'gender' => 'Female',
            'blood_type' => 'O+',
            'street_address' => '12 Main Street',
            'barangay' => 'Poblacion',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'privacy_version' => config('privacy.version'),
            'privacy_acknowledged' => '1',
            'purpose_accepted' => '1',
        ])->assertOk();

        Mail::assertSent(OtpMail::class);
        $pending = $this->app['session.store']->get('pending_donor_signup');
        $this->assertIsArray($pending);
        $this->assertArrayHasKey('otp_hash', $pending);
        $this->assertArrayNotHasKey('otp', $pending);
        $this->assertNotSame('Password123', $pending['payload']['password']);
        $this->assertDatabaseCount('donor_authentication', 0);

        $sentOtp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$sentOtp): bool {
            $sentOtp = $mail->otp;

            return true;
        });
        $wrongOtp = $sentOtp === '000000' ? '000001' : '000000';

        $this->postJson(route('donor.signup.confirm-otp'), ['otp' => $wrongOtp])
            ->assertUnprocessable();

        $this->assertDatabaseCount('donor_authentication', 0);
        $this->assertSame(1, (int) $this->app['session.store']->get('pending_donor_signup.attempts'));
    }
}
