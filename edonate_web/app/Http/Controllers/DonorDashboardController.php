<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\BloodType;
use App\Models\DonationRecord;
use App\Models\Donor;
use App\Models\DonorVerification;
use App\Models\Location;
use App\Models\Notification;
use App\Services\GeocodingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

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

        $notificationQuery = Notification::query()
            ->where('donor_id', $donor->donor_id)
            ->when(Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'recipient_type'), function ($query): void {
                $query->where(function ($builder): void {
                    $builder->whereNull('recipient_type')
                    ->orWhereIn('recipient_type', ['donor', 'all_donors']);
                });
            });

        $alertsCount = (clone $notificationQuery)
            ->where('is_read', 0)
            ->count();

        $notificationBanner = (clone $notificationQuery)
            ->where('is_read', 0)
            ->orderByDesc('created_at')
            ->orderByDesc('notification_id')
            ->first();

        $latestVerification = DonorVerification::query()
            ->where('donor_id', $donor->donor_id)
            ->orderByDesc('verification_id')
            ->first();

        $identityVerificationStatus = $this->verificationStatus($donor);

        $user = (object) [
            'first_name' => $donor->first_name,
            'last_name' => $donor->last_name,
            'blood_type' => $bloodType ?? '-',
            'blood_type_status' => $this->bloodTypeStatus($donor),
            'total_donations' => $totalDonations,
        ];

        $navLinks = [
            ['key' => 'home', 'label' => 'Home', 'href' => route('donor.dashboard')],
            ['key' => 'book', 'label' => 'Book Appointment', 'href' => route('donor.book-appointment')],
            ['key' => 'eligibility', 'label' => 'Check Eligibility', 'href' => route('donor.check-eligibility')],
            ['key' => 'verification', 'label' => 'Verify Identity', 'href' => route('donor.verification.index')],
            ['key' => 'history', 'label' => 'History', 'href' => route('donor.history')],
            ['key' => 'alerts', 'label' => 'Alerts', 'href' => route('donor.alerts')],
        ];

        return view('donor.dashboard', [
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
            'notificationBanner' => $notificationBanner,
            'bloodTypes' => BloodType::query()->orderBy('blood_type')->pluck('blood_type'),
            'identityVerificationStatus' => $identityVerificationStatus,
            'latestVerification' => $latestVerification,
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

        $minimumAge = (int) config('privacy.minimum_age', 18);
        $minimumBirthdate = now()->subYears($minimumAge)->toDateString();

        $validated = $request->validate([
            'phone' => ['required', 'regex:/^(\+63|0)\d{10}$/'],
            'birthdate' => ['required', 'date', 'before_or_equal:'.$minimumBirthdate],
            'gender' => ['required', 'string', 'max:20'],
            'blood_type' => ['required', 'string', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']), Rule::exists('blood_types', 'blood_type')],
            'street_address' => ['required', 'string', 'max:150'],
            'barangay' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
        ], [
            'birthdate.before_or_equal' => "You must be at least {$minimumAge} years old to create an account and donate blood.",
        ]);

        $bloodType = BloodType::query()
            ->where('blood_type', $validated['blood_type'])
            ->firstOrFail();
        $bloodTypeStatus = strtolower(trim((string) ($donor->blood_type_status ?? 'not_yet_determined')));

        if ($bloodTypeStatus === 'verified' && (int) $donor->blood_type_id !== (int) $bloodType->blood_type_id) {
            return redirect('/dashboard')->withErrors([
                'blood_type' => 'Your verified blood type can only be changed through a completed donation review.',
            ]);
        }

        DB::beginTransaction();

        try {
            $shouldGeocodeLocation = false;

            $location = $donor->location_id
                ? Location::query()->find($donor->location_id)
                : null;

            if ($location) {
                $addressChanged = $this->locationAddressChanged($location, [
                    'street_address' => $validated['street_address'],
                    'barangay_name' => $validated['barangay'],
                    'city' => $validated['city'],
                    'province' => $validated['province'],
                ]);

                $location->update([
                    'street_address' => $validated['street_address'],
                    'barangay_name' => $validated['barangay'],
                    'city' => $validated['city'],
                    'province' => $validated['province'],
                    'latitude' => $addressChanged ? null : $location->latitude,
                    'longitude' => $addressChanged ? null : $location->longitude,
                ]);

                $shouldGeocodeLocation = $addressChanged || ! app(GeocodingService::class)->hasCoordinates($location);
            } else {
                $location = Location::query()->create([
                    'street_address' => $validated['street_address'],
                    'barangay_name' => $validated['barangay'],
                    'city' => $validated['city'],
                    'province' => $validated['province'],
                ]);
                $shouldGeocodeLocation = true;
            }

            $donorPayload = [
                'contact_number' => $validated['phone'],
                'birthdate' => $validated['birthdate'],
                'gender' => $validated['gender'],
                'blood_type_id' => $bloodType->blood_type_id,
                'location_id' => $location->location_id,
            ];

            if ($bloodTypeStatus !== 'verified') {
                $donorPayload['blood_type_status'] = 'self_reported';
                $donorPayload['blood_type_verified_by_admin_id'] = null;
                $donorPayload['blood_type_verified_at'] = null;
            }

            $donor->update($donorPayload);

            $request->session()->put('donor_name', trim($donor->first_name . ' ' . $donor->last_name));

            DB::commit();

            if ($shouldGeocodeLocation) {
                app(GeocodingService::class)->geocodeAndSave($location);
            }

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

    private function verificationStatus(Donor $donor): string
    {
        if (! Schema::hasColumn('donors', 'verification_status')) {
            return 'unverified';
        }

        $status = strtolower(trim((string) ($donor->verification_status ?? 'unverified')));

        return in_array($status, ['unverified', 'pending', 'verified', 'rejected'], true) ? $status : 'unverified';
    }

    private function bloodTypeStatus(Donor $donor): string
    {
        $status = strtolower(trim((string) ($donor->blood_type_status ?? 'not_yet_determined')));

        return in_array($status, ['verified', 'self_reported', 'not_yet_determined'], true)
            ? $status
            : 'not_yet_determined';
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function locationAddressChanged(Location $location, array $payload): bool
    {
        foreach ($payload as $field => $value) {
            if (trim((string) ($location->{$field} ?? '')) !== trim((string) $value)) {
                return true;
            }
        }

        return false;
    }
}
