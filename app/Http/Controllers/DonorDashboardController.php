<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\BloodType;
use App\Models\Donor;
use App\Models\DonorAuthentication;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DonorDashboardController extends Controller
{
    /**
     * Display donor dashboard and profile-completion prompt.
     */
    public function index(Request $request)
    {
        $donorId = (int) $request->session()->get('donor_id');

        if ($donorId <= 0) {
            return redirect('/login')->with('error', 'Please log in to continue.');
        }

        $donor = Donor::query()->find($donorId);
        if (!$donor) {
            $request->session()->forget(['donor_auth_id', 'donor_id', 'donor_email', 'donor_name']);
            return redirect('/login')->with('error', 'Your account could not be found. Please log in again.');
        }

        $location = $donor->location_id ? Location::query()->find($donor->location_id) : null;
        $profileComplete = $this->isProfileComplete($donor, $location);
        $auth = DonorAuthentication::query()->find((int) $request->session()->get('donor_auth_id'));
        $otpVerified = (bool) ($auth?->is_verified);
        $termsAccepted = (bool) $request->session()->get('terms_accepted', false);
        $accessUnlocked = $profileComplete && $otpVerified && $termsAccepted;

        return view('dashboard', [
            'donor' => $donor,
            'location' => $location,
            'profileComplete' => $profileComplete,
            'otpVerified' => $otpVerified,
            'termsAccepted' => $termsAccepted,
            'accessUnlocked' => $accessUnlocked,
            'bloodTypes' => BloodType::query()->orderBy('blood_type')->pluck('blood_type'),
        ]);
    }

    /**
     * Accept Terms and Privacy for current session.
     */
    public function acceptTerms(Request $request): RedirectResponse
    {
        if ((int) $request->session()->get('donor_id') <= 0) {
            return redirect('/login')->with('error', 'Please log in to continue.');
        }

        $request->session()->put('terms_accepted', true);

        return redirect('/dashboard')->with('success', 'Terms accepted. You can proceed with the remaining requirements.');
    }

    /**
     * Send OTP to currently signed-in donor email for access unlock.
     */
    public function sendAccessOtp(Request $request): RedirectResponse
    {
        $authId = (int) $request->session()->get('donor_auth_id');

        if ($authId <= 0) {
            return redirect('/login')->with('error', 'Please log in to continue.');
        }

        $auth = DonorAuthentication::query()->find($authId);
        if (!$auth) {
            $request->session()->forget(['donor_auth_id', 'donor_id', 'donor_email', 'donor_name', 'auth_provider', 'terms_accepted']);
            return redirect('/login')->with('error', 'Your account could not be found. Please log in again.');
        }

        $otpCode = (string) random_int(100000, 999999);
        $request->session()->put('pending_access_otp', [
            'otp_hash' => hash('sha256', $otpCode),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'attempts' => 0,
        ]);

        try {
            Mail::to($auth->email)->send(new OtpMail($otpCode));

            return redirect('/dashboard')->with('success', 'A verification OTP has been sent to your email.');
        } catch (\Throwable $exception) {
            report($exception);

            return redirect('/dashboard')->with('error', 'Unable to send OTP right now. Please try again.');
        }
    }

    /**
     * Verify dashboard OTP and unlock access gate.
     */
    public function verifyAccessOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $authId = (int) $request->session()->get('donor_auth_id');
        if ($authId <= 0) {
            return redirect('/login')->with('error', 'Please log in to continue.');
        }

        $pending = $request->session()->get('pending_access_otp');
        if (!is_array($pending) || !isset($pending['otp_hash'], $pending['expires_at'])) {
            return redirect('/dashboard')->with('error', 'No OTP request found. Please send a new OTP first.');
        }

        if (now()->timestamp > (int) $pending['expires_at']) {
            $request->session()->forget('pending_access_otp');
            return redirect('/dashboard')->with('error', 'OTP expired. Please request a new code.');
        }

        $attempts = (int) ($pending['attempts'] ?? 0);
        if ($attempts >= 5) {
            $request->session()->forget('pending_access_otp');
            return redirect('/dashboard')->with('error', 'Too many invalid attempts. Request a new OTP.');
        }

        $provided = (string) $request->input('otp');
        if (!hash_equals((string) $pending['otp_hash'], hash('sha256', $provided))) {
            $pending['attempts'] = $attempts + 1;
            $request->session()->put('pending_access_otp', $pending);

            return redirect('/dashboard')->with('error', 'Invalid OTP. Please try again.');
        }

        DonorAuthentication::query()
            ->where('auth_id', $authId)
            ->update([
                'is_verified' => true,
                'verified_at' => now(),
                'verification_token' => null,
                'verification_sent_at' => now(),
            ]);

        $request->session()->forget('pending_access_otp');

        return redirect('/dashboard')->with('success', 'OTP verified. Access requirements are updated.');
    }

    /**
     * Store donor profile fields required for full account usage.
     */
    public function completeProfile(Request $request): RedirectResponse
    {
        $donorId = (int) $request->session()->get('donor_id');

        if ($donorId <= 0) {
            return redirect('/login')->with('error', 'Please log in to continue.');
        }

        $donor = Donor::query()->find($donorId);
        if (!$donor) {
            $request->session()->forget(['donor_auth_id', 'donor_id', 'donor_email', 'donor_name']);
            return redirect('/login')->with('error', 'Your account could not be found. Please log in again.');
        }

        $validated = $request->validate([
            'phone' => ['required', 'regex:/^(\+63|0)\d{10}$/'],
            'birthdate' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'string', 'max:20'],
            'blood_type' => ['required', 'string', 'max:5'],
            'street_address' => ['required', 'string', 'max:150'],
            'barangay' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
        ]);

        DB::beginTransaction();

        try {
            $bloodType = BloodType::query()->firstOrCreate([
                'blood_type' => $validated['blood_type'],
            ]);

            $location = $donor->location_id
                ? Location::query()->find($donor->location_id)
                : null;

            if ($location) {
                $location->update([
                    'street_address' => $validated['street_address'],
                    'barangay_name' => $validated['barangay'],
                    'city' => $validated['city'],
                    'province' => $validated['province'],
                ]);
            } else {
                $location = Location::query()->create([
                    'street_address' => $validated['street_address'],
                    'barangay_name' => $validated['barangay'],
                    'city' => $validated['city'],
                    'province' => $validated['province'],
                ]);
            }

            $donor->update([
                'contact_number' => $validated['phone'],
                'birthdate' => $validated['birthdate'],
                'gender' => $validated['gender'],
                'blood_type_id' => $bloodType->blood_type_id,
                'location_id' => $location->location_id,
            ]);

            $request->session()->put('donor_name', trim($donor->first_name . ' ' . $donor->last_name));

            DB::commit();

            return redirect('/dashboard')->with('success', 'Profile completed successfully.');
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return redirect('/dashboard')->with('error', 'Unable to save your profile right now. Please try again.');
        }
    }

    private function isProfileComplete(Donor $donor, ?Location $location): bool
    {
        return !empty($donor->contact_number)
            && !empty($donor->birthdate)
            && !empty($donor->gender)
            && !empty($donor->blood_type_id)
            && $location !== null
            && !empty($location->street_address)
            && !empty($location->barangay_name)
            && !empty($location->city)
            && !empty($location->province);
    }
}
