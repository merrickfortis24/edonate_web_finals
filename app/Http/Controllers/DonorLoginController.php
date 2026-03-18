<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\DonorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DonorLoginController extends Controller
{
    /**
     * Display donor login page.
     */
    public function create()
    {
        if (session()->has('donor_auth_id')) {
            return redirect('/dashboard');
        }

        return view('donor.login');
    }

    /**
     * Authenticate donor credentials.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
            'password' => ['required', 'string'],
            'terms' => ['accepted'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'Password is required.',
            'terms.accepted' => 'You must agree to the Terms of Service and Privacy Policy before logging in.',
        ]);

        $auth = DonorAuthentication::query()
            ->where('email', $validated['email'])
            ->first();

        if (!$auth || !Hash::check($validated['password'], $auth->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        if (!$auth->is_verified) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Your account is not verified yet. Please complete OTP verification.']);
        }

        $donor = Donor::query()->find($auth->donor_id);

        $request->session()->regenerate();
        $request->session()->put([
            'donor_auth_id' => $auth->auth_id,
            'donor_id' => $auth->donor_id,
            'donor_email' => $auth->email,
            'donor_name' => $donor ? trim($donor->first_name . ' ' . $donor->last_name) : null,
            'auth_provider' => 'password',
            'terms_accepted' => true,
        ]);

        return redirect('/dashboard')->with('success', 'Logged in successfully.');
    }

    /**
     * Log donor out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['donor_auth_id', 'donor_id', 'donor_email', 'donor_name', 'auth_provider', 'terms_accepted']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'You have been logged out.');
    }
}
