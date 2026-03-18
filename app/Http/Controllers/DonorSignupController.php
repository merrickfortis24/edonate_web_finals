<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDonorRegistrationRequest;
use App\Mail\OtpMail;
use App\Models\BloodType;
use App\Models\Donor;
use App\Models\DonorAuthentication;
use App\Models\Location;
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

        try {
            $this->persistDonorRegistration($validated);

            return redirect('/login')->with('success', 'Registration completed successfully. Please log in to continue.');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', 'We could not complete your registration at the moment. Please try again.');
        }
    }

    /**
     * Validate signup form, send OTP, and store pending signup payload in session.
     */
    public function sendOtp(Request $request): JsonResponse
    {
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

        $validated = $validator->validated();
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
            $donor = $this->persistDonorRegistration($payload);

            // Mark the authentication record as verified since OTP was confirmed.
            DonorAuthentication::query()
                ->where('donor_id', $donor->donor_id)
                ->update([
                    'is_verified' => true,
                    'verified_at' => now(),
                    'verification_sent_at' => now(),
                    'verification_token' => null,
                ]);

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
        DB::beginTransaction();

        try {
            $bloodType = BloodType::firstOrCreate([
                'blood_type' => $validated['blood_type'],
            ]);

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

            DB::commit();

            return $donor;
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }
}
