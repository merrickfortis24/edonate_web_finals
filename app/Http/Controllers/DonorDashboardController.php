<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\BloodType;
use App\Models\DonationRecord;
use App\Models\Donor;
use App\Models\Location;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $bloodType = $donor->blood_type_id
            ? BloodType::query()->where('blood_type_id', $donor->blood_type_id)->value('blood_type')
            : null;

        $totalDonations = DonationRecord::query()
            ->where('donor_id', $donor->donor_id)
            ->count();

        $latestDonationDate = DonationRecord::query()
            ->where('donor_id', $donor->donor_id)
            ->max('donation_date');

        $nextEligibleDate = $latestDonationDate
            ? Carbon::parse($latestDonationDate)->addDays(56)->format('F j, Y')
            : 'Eligible now';

        $upcomingAppointments = Appointment::query()
            ->where('donor_id', $donor->donor_id)
            ->whereDate('appointment_date', '>=', Carbon::today())
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(8)
            ->get();

        $alertsCount = Notification::query()
            ->where('donor_id', $donor->donor_id)
            ->where('is_read', 0)
            ->count();

        $user = (object) [
            'first_name' => $donor->first_name,
            'last_name' => $donor->last_name,
            'blood_type' => $bloodType ?? '-',
            'total_donations' => $totalDonations,
        ];

        $navLinks = [
            ['key' => 'home', 'label' => 'Home', 'href' => route('donor.dashboard')],
            ['key' => 'book', 'label' => 'Book Appointment', 'href' => route('donor.book-appointment')],
            ['key' => 'eligibility', 'label' => 'Check Eligibility', 'href' => route('donor.check-eligibility')],
            ['key' => 'history', 'label' => 'History', 'href' => route('donor.history')],
            ['key' => 'alerts', 'label' => 'Alerts', 'href' => route('donor.alerts')],
        ];

        return view('dashboard', [
            'donor' => $donor,
            'user' => $user,
            'location' => $location,
            'profileComplete' => $profileComplete,
            'totalDonations' => $totalDonations,
            'nextEligibleDate' => $nextEligibleDate,
            'livesImpacted' => $totalDonations * 3,
            'upcomingAppointments' => $upcomingAppointments,
            'navLinks' => $navLinks,
            'activeNav' => 'home',
            'alertsCount' => $alertsCount,
            'bloodTypes' => BloodType::query()->orderBy('blood_type')->pluck('blood_type'),
        ]);
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
