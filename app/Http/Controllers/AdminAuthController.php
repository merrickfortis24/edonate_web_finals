<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    /**
     * Display admin login page.
     */
    public function create()
    {
        if (session()->has('admin_id')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.admin_login');
    }

    /**
     * Handle admin login and redirect to dashboard.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $admin = DB::table('admins')
            ->where('email', $validated['email'])
            ->first();

        if (!$admin || !Hash::check($validated['password'], $admin->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        $request->session()->regenerate();
        $request->session()->put([
            'admin_id' => $admin->admin_id,
            'admin_username' => $admin->username,
            'admin_full_name' => $admin->full_name,
            'admin_role' => $admin->role,
        ]);

        return redirect()->route('admin.dashboard');
    }

    /**
     * Display admin dashboard.
     */
    public function dashboard(Request $request)
    {
        if (!$request->session()->has('admin_id')) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.admin_dashboard');
    }

    /**
     * Display user management page.
     */
    public function users(Request $request)
    {
        if (!$request->session()->has('admin_id')) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.user_management');
    }

    /**
     * Display appointment management page.
     */
    public function appointments(Request $request)
    {
        if (!$request->session()->has('admin_id')) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.appointment_management');
    }

    /**
     * Display donation records page.
     */
    public function donationRecords(Request $request)
    {
        if (!$request->session()->has('admin_id')) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.donor_records');
    }

    /**
     * Display blood availability mapping page.
     */
    public function bloodAvailabilityMapping(Request $request)
    {
        if (!$request->session()->has('admin_id')) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.blood_availability_mapping');
    }

    /**
     * Display notification center page.
     */
    public function notificationCenter(Request $request)
    {
        if (!$request->session()->has('admin_id')) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.notification_center');
    }

    /**
     * Display report and analytics page.
     */
    public function reportAnalytics(Request $request)
    {
        if (!$request->session()->has('admin_id')) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.report_analytics');
    }

    /**
     * Log admin out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['admin_id', 'admin_username', 'admin_full_name', 'admin_role']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'You have been logged out.');
    }
}
