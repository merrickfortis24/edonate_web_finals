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
use Carbon\CarbonPeriod;
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

        $timeSlots = [
            '08:00',
            '09:00',
            '10:00',
            '11:00',
            '13:00',
            '14:00',
            '15:00',
        ];

        [$availableDates, $fullyBookedDates] = $this->buildCalendarAvailability(
            (int) $context['donor']->donor_id,
            Carbon::today(),
            60,
            count($timeSlots)
        );

        $appointments = Appointment::query()
            ->where('donor_id', $context['donor']->donor_id)
            ->whereDate('appointment_date', '>=', Carbon::today())
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(10)
            ->get();

        return view('portal.book-appointment', $context + [
            'appointments' => $appointments,
            'timeSlots' => $timeSlots,
            'availableDates' => $availableDates,
            'fullyBookedDates' => $fullyBookedDates,
        ]);
    }

    public function storeAppointment(Request $request): RedirectResponse
    {
        $context = $this->buildContext($request, 'book');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $timeSlots = [
            '08:00',
            '09:00',
            '10:00',
            '11:00',
            '13:00',
            '14:00',
            '15:00',
        ];

        [$availableDates, $fullyBookedDates] = $this->buildCalendarAvailability(
            (int) $context['donor']->donor_id,
            Carbon::today(),
            60,
            count($timeSlots)
        );

        $validated = $request->validate([
            'donation_date' => ['required', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', 'date_format:H:i'],
            'donation_center' => ['nullable', 'string', 'max:150'],
        ]);

        if (!in_array($validated['donation_date'], $availableDates, true)) {
            return redirect()
                ->route('donor.book-appointment')
                ->withInput()
                ->with('error', 'The selected date is not available for booking.');
        }

        if (!in_array($validated['time_slot'], $timeSlots, true)) {
            return redirect()
                ->route('donor.book-appointment')
                ->withInput()
                ->with('error', 'Please choose a valid time slot.');
        }

        $slotTaken = Appointment::query()
            ->where('appointment_date', $validated['donation_date'])
            ->where('appointment_time', $validated['time_slot'])
            ->exists();

        if ($slotTaken) {
            return redirect()
                ->route('donor.book-appointment')
                ->withInput()
                ->with('error', 'That schedule is already taken. Please select another time.');
        }

        Appointment::query()->create([
            'donor_id' => $context['donor']->donor_id,
            'appointment_date' => $validated['donation_date'],
            'appointment_time' => $validated['time_slot'],
            'status' => 'pending',
            'created_at' => Carbon::now(),
            'admin_id' => null,
        ]);

        return redirect()
            ->route('donor.book-appointment')
            ->with('success', 'Appointment booked successfully.');
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

    private function buildCalendarAvailability(int $donorId, Carbon $startDate, int $days, int $maxPerDay): array
    {
        $appointmentsPerDay = Appointment::query()
            ->whereDate('appointment_date', '>=', $startDate)
            ->whereDate('appointment_date', '<=', (clone $startDate)->addDays($days))
            ->selectRaw('appointment_date, COUNT(*) as total')
            ->groupBy('appointment_date')
            ->pluck('total', 'appointment_date');

        $period = CarbonPeriod::create($startDate, (clone $startDate)->addDays($days));
        $availableDates = [];
        $fullyBookedDates = [];

        foreach ($period as $date) {
            $key = $date->toDateString();
            $count = (int) ($appointmentsPerDay[$key] ?? 0);

            if ($count >= $maxPerDay) {
                $fullyBookedDates[] = $key;
                continue;
            }

            $availableDates[] = $key;
        }

        return [$availableDates, $fullyBookedDates];
    }
}