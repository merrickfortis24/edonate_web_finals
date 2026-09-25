<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDonorRegistrationRequest;
use App\Mail\OtpMail;
use App\Models\BloodType;
use App\Models\Donor;
use App\Models\DonorAuthentication;
use App\Models\Location;
use App\Services\AdminNotificationService;
use App\Services\GeocodingService;
use App\Services\PrivacyConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class DonorSignupController extends Controller
{
    /**
     * Display donor sign-up form.
     */
    public function create()
    {
        return view('donor.signup');
    }

    /**
     * Real-time email availability check for signup form.
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:150'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'available' => false,
                'message' => 'Please enter a valid email address.',
            ], 422);
        }

        $email = (string) $validator->validated()['email'];
        $isTaken = DonorAuthentication::query()->where('email', $email)->exists();

        return response()->json([
            'available' => !$isTaken,
            'message' => $isTaken
                ? 'This email is already registered. Please use another email or log in.'
                : 'Email is available.',
        ]);
    }

    /**
     * Store donor registration.
     */
    public function store(StoreDonorRegistrationRequest $request)
    {
        $validated = $request->validated();

        if (! app(PrivacyConsent::class)->readyForCollection()) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', 'Registration is temporarily disabled pending the operator’s privacy review.');
        }

        $response = $this->issueOtp($validated);
        $result = $response->getData(true);

        if (! $response->isSuccessful()) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', (string) ($result['message'] ?? 'We could not send the OTP right now. Please try again.'));
        }

        return redirect()->route('donor.signup')
            ->withInput($request->except(['password', 'password_confirmation']))
            ->with('otp_sent', true)
            ->with('success', (string) ($result['message'] ?? 'OTP sent. Please check your email.'));
    }

    /**
     * Validate signup form, send OTP, and store pending signup payload in session.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        if (! app(PrivacyConsent::class)->readyForCollection()) {
            return response()->json(['message' => 'Registration is temporarily disabled pending the operator’s privacy review.'], 503);
        }

        $formRequest = new StoreDonorRegistrationRequest();
        $validator = Validator::make(
            $request->all(),
            $formRequest->rules(),
            $formRequest->messages(),
            $formRequest->attributes()
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Please correct the highlighted fields.',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $this->issueOtp($validator->validated());
    }

    /**
     * Keep registration data pending until the email OTP is confirmed.
     *
     * @param  array<string, mixed>  $validated
     */
    private function issueOtp(array $validated): JsonResponse
    {
        $otpCode = (string) random_int(100000, 999999);
        $payload = $validated;
        $payload['password'] = Crypt::encryptString($validated['password']);

        session([
            'pending_donor_signup' => [
                'payload' => $payload,
                'otp_hash' => hash('sha256', $otpCode),
                'expires_at' => now()->addMinutes(10)->timestamp,
                'attempts' => 0,
            ],
        ]);

        try {
            Mail::to($validated['email'])->send(new OtpMail($otpCode));

            return response()->json([
                'message' => 'A 6-digit OTP has been sent to your email address.',
            ]);
        } catch (Throwable $exception) {
            session()->forget('pending_donor_signup');
            report($exception);

            return response()->json([
                'message' => 'We could not send the OTP email right now. Please try again.',
            ], 500);
        }
    }

    /**
     * Confirm OTP and complete donor registration transaction.
     */
    public function confirmOtp(Request $request): JsonResponse
    {
        if (! app(PrivacyConsent::class)->readyForCollection()) {
            return response()->json(['message' => 'Registration is temporarily disabled pending the operator’s privacy review.'], 503);
        }
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $pending = session('pending_donor_signup');

        if (!is_array($pending) || !isset($pending['otp_hash'], $pending['expires_at'], $pending['payload'])) {
            return response()->json([
                'message' => 'No pending registration was found. Please submit the form again.',
            ], 422);
        }

        if (now()->timestamp > (int) $pending['expires_at']) {
            session()->forget('pending_donor_signup');

            return response()->json([
                'message' => 'OTP has expired. Please request a new one.',
            ], 422);
        }

        $attempts = (int) ($pending['attempts'] ?? 0);

        if ($attempts >= 5) {
            session()->forget('pending_donor_signup');

            return response()->json([
                'message' => 'Too many invalid OTP attempts. Please submit the form again.',
            ], 429);
        }

        if (!hash_equals((string) $pending['otp_hash'], hash('sha256', (string) $request->string('otp')))) {
            $pending['attempts'] = $attempts + 1;
            session(['pending_donor_signup' => $pending]);

            return response()->json([
                'message' => 'Invalid OTP. Please check the code and try again.',
            ], 422);
        }

        $payload = $pending['payload'];
        if (($payload['privacy_version'] ?? null) !== config('privacy.version')
            || ! in_array($payload['privacy_acknowledged'] ?? null, [true, 1, '1', 'yes', 'on', 'true'], true)
            || ! in_array($payload['purpose_accepted'] ?? null, [true, 1, '1', 'yes', 'on', 'true'], true)) {
            session()->forget('pending_donor_signup');
            return response()->json(['message' => 'Review the current privacy notice and resubmit the registration form.'], 422);
        }

        try {
            $payload['password'] = Crypt::decryptString((string) $payload['password']);
        } catch (Throwable $exception) {
            session()->forget('pending_donor_signup');
            report($exception);

            return response()->json([
                'message' => 'Pending registration data is invalid. Please submit the form again.',
            ], 422);
        }

        if (DonorAuthentication::query()->where('email', $payload['email'])->exists()) {
            session()->forget('pending_donor_signup');

            return response()->json([
                'message' => 'This email is already registered. Please use another email or log in.',
            ], 422);
        }

        try {
            $donor = DB::transaction(function () use ($payload, $request) {
                $donor = $this->persistDonorRegistration($payload);

                // Registration is only persisted after the pending OTP is verified.
                DonorAuthentication::query()
                    ->where('donor_id', $donor->donor_id)
                    ->update([
                        'is_verified' => true,
                        'verified_at' => now(),
                        'verification_sent_at' => now(),
                        'verification_token' => null,
                    ]);

                app(PrivacyConsent::class)->record($request, 'registration', [
                    'terms' => true, 'privacy_notice' => true, 'purpose' => true, 'email_verified' => true,
                ], 'donor:'.$donor->donor_id);
                return $donor;
            });

            // External geocoding and notification delivery must not leave a partial
            // donor registration if either integration is temporarily unavailable.
            $this->runPostRegistrationIntegrations($donor);

            session()->forget('pending_donor_signup');

            return response()->json([
                'message' => 'Registration completed successfully.',
                'redirect_url' => url('/login'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'We could not complete your registration at the moment. Please try again.',
            ], 500);
        }
    }

    /**
     * Persist donor registration in a single transaction.
     *
     * @param  array<string, mixed>  $validated
     */
    private function persistDonorRegistration(array $validated)
    {
        $bloodType = BloodType::query()
            ->where('blood_type', $validated['blood_type'])
            ->firstOrFail();

        $location = Location::create([
            'street_address' => $validated['street_address'],
            'barangay_name' => $validated['barangay'],
            'city' => $validated['city'],
            'province' => $validated['province'],
        ]);

        $donor = Donor::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'gender' => $validated['gender'],
            'birthdate' => $validated['birthdate'],
            'contact_number' => $validated['phone'],
            'blood_type_id' => $bloodType->blood_type_id,
            'blood_type_status' => 'self_reported',
            'blood_type_verified_by_admin_id' => null,
            'blood_type_verified_at' => null,
            'location_id' => $location->location_id,
            'date_registered' => now(),
        ]);

        DonorAuthentication::create([
            'donor_id' => $donor->donor_id,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_verified' => false,
            'created_at' => now(),
        ]);

        return $donor->setRelation('location', $location);
    }

    private function runPostRegistrationIntegrations(Donor $donor): void
    {
        try {
            $location = $donor->relationLoaded('location')
                ? $donor->getRelation('location')
                : Location::query()->find($donor->location_id);

            if ($location) {
                app(GeocodingService::class)->geocodeAndSave($location);
            }

            $donorName = trim($donor->first_name.' '.$donor->last_name);
            app(AdminNotificationService::class)->createAdminEvent(
                'donor_registration',
                'New Donor Registration',
                "{$donorName} has registered as a new donor.",
                'donor',
                (int) $donor->donor_id
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
