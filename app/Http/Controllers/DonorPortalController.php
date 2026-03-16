<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\BloodType;
use App\Models\DonationRecord;
use App\Models\Donor;
use App\Models\EligibilityStatus;
use App\Models\Location;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DonorPortalController extends Controller
{
    public function bookAppointment(Request $request)
    {
        $context = $this->buildContext($request, 'book');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $appointments = Appointment::query()
            ->where('donor_id', $context['donor']->donor_id)
            ->whereDate('appointment_date', '>=', Carbon::today())
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(10)
            ->get();

        return view('portal.book-appointment', $context + [
            'appointments' => $appointments,
        ]);
    }

    public function checkEligibility(Request $request)
    {
        $context = $this->buildContext($request, 'eligibility');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $latestEligibility = EligibilityStatus::query()
            ->where('donor_id', $context['donor']->donor_id)
            ->orderByDesc('eligibility_id')
            ->first();

        $latestDonationDate = DonationRecord::query()
            ->where('donor_id', $context['donor']->donor_id)
            ->max('donation_date');

        $nextEligibleDate = $latestEligibility?->next_eligible_date
            ?? ($latestDonationDate ? Carbon::parse($latestDonationDate)->addDays(56)->toDateString() : null);

        return view('portal.check-eligibility', $context + [
            'latestEligibility' => $latestEligibility,
            'latestDonationDate' => $latestDonationDate,
            'nextEligibleDate' => $nextEligibleDate,
        ]);
    }

    public function history(Request $request)
    {
        $context = $this->buildContext($request, 'history');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $donationHistory = DonationRecord::query()
            ->where('donor_id', $context['donor']->donor_id)
            ->orderByDesc('donation_date')
            ->limit(20)
            ->get();

        return view('portal.history', $context + [
            'donationHistory' => $donationHistory,
        ]);
    }

    public function alerts(Request $request)
    {
        $context = $this->buildContext($request, 'alerts');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $alerts = Notification::query()
            ->where('donor_id', $context['donor']->donor_id)
            ->orderByDesc('created_at')
            ->orderByDesc('notification_id')
            ->limit(20)
            ->get();

        return view('portal.alerts', $context + [
            'alerts' => $alerts,
        ]);
    }

    private function buildContext(Request $request, string $activeNav): array|RedirectResponse
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
        $bloodType = $donor->blood_type_id
            ? BloodType::query()->where('blood_type_id', $donor->blood_type_id)->value('blood_type')
            : null;

        $totalDonations = DonationRecord::query()
            ->where('donor_id', $donor->donor_id)
            ->count();

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

        return [
            'donor' => $donor,
            'location' => $location,
            'user' => $user,
            'navLinks' => $this->navLinks(),
            'activeNav' => $activeNav,
            'alertsCount' => $alertsCount,
            'totalDonations' => $totalDonations,
        ];
    }

    private function navLinks(): array
    {
        return [
            ['key' => 'home', 'label' => 'Home', 'href' => route('donor.dashboard')],
            ['key' => 'book', 'label' => 'Book Appointment', 'href' => route('donor.book-appointment')],
            ['key' => 'eligibility', 'label' => 'Check Eligibility', 'href' => route('donor.check-eligibility')],
            ['key' => 'history', 'label' => 'History', 'href' => route('donor.history')],
            ['key' => 'alerts', 'label' => 'Alerts', 'href' => route('donor.alerts')],
        ];
    }
}