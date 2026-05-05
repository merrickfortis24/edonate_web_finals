<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\BloodType;
use App\Models\DonationRecord;
use App\Models\Donor;
use App\Models\DonorScreeningAnswer;
use App\Models\EligibilityQuestion;
use App\Models\EligibilityStatus;
use App\Models\Location;
use App\Models\Notification;
use App\Services\AdminNotificationService;
use App\Services\EligibilityEvaluator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class DonorPortalController extends Controller
{
    public function bookAppointment(Request $request)
    {
        $context = $this->buildContext($request, 'book');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $canBookAppointment = $this->donorCanBookAppointment($context['donor']);
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
            'canBookAppointment' => $canBookAppointment,
            'identityVerificationStatus' => $this->donorVerificationStatus($context['donor']),
        ]);
    }

    public function storeAppointment(Request $request): RedirectResponse
    {
        $context = $this->buildContext($request, 'book');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        if (! $this->donorCanBookAppointment($context['donor'])) {
            return redirect()
                ->route('donor.book-appointment')
                ->withInput()
                ->with('error', 'Please verify your identity before booking a donation appointment.');
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

        $appointment = Appointment::query()->create([
            'donor_id' => $context['donor']->donor_id,
            'appointment_date' => $validated['donation_date'],
            'appointment_time' => $validated['time_slot'],
            'status' => 'pending',
            'created_at' => Carbon::now(),
            'admin_id' => null,
        ]);

        $donorName = trim((string) $context['donor']->first_name . ' ' . (string) $context['donor']->last_name);
        app(AdminNotificationService::class)->createAdminEvent(
            'appointment_booked',
            'Appointment Booked',
            "{$donorName} booked an appointment for {$validated['donation_date']} at {$validated['time_slot']}.",
            'appointment',
            (int) $appointment->appointment_id
        );

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
            'screeningQuestions' => $this->activeScreeningQuestions(),
        ]);
    }

    public function submitEligibility(Request $request, EligibilityEvaluator $evaluator): RedirectResponse
    {
        $context = $this->buildContext($request, 'eligibility');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $questions = $this->activeScreeningQuestions();
        if ($questions->isEmpty()) {
            return redirect()
                ->route('donor.check-eligibility')
                ->with('error', 'No active screening questions are available right now.');
        }

        $rules = [
            'answers' => ['required', 'array'],
            'followups' => ['nullable', 'array'],
        ];

        foreach ($questions as $question) {
            $questionId = (int) $question->question_id;
            $rules["answers.{$questionId}"] = ['required', 'string', Rule::in(['yes', 'no'])];
            $rules["followups.{$questionId}"] = ['nullable', 'string', 'max:1000'];
        }

        $validated = $request->validate($rules, [
            'answers.required' => 'Please answer all screening questions before submitting.',
            'answers.*.required' => 'Please answer all screening questions before submitting.',
            'answers.*.in' => 'Please choose Yes or No for every screening question.',
        ]);

        $answers = $validated['answers'] ?? [];
        $followups = $validated['followups'] ?? [];
        $result = $evaluator->evaluate($answers);
        $donorId = (int) $context['donor']->donor_id;
        $latestDonationDate = DonationRecord::query()
            ->where('donor_id', $donorId)
            ->max('donation_date');

        $eligibility = null;

        DB::transaction(function () use (
            $answers,
            $followups,
            $questions,
            $result,
            $donorId,
            $latestDonationDate,
            &$eligibility
        ): void {
            $eligibility = EligibilityStatus::query()
                ->where('donor_id', $donorId)
                ->orderByDesc('eligibility_id')
                ->lockForUpdate()
                ->first();

            $payload = $this->buildEligibilityStatusPayload($result, $latestDonationDate);

            if ($eligibility instanceof EligibilityStatus) {
                $eligibility->fill($payload);
                $eligibility->save();
            } else {
                $eligibility = EligibilityStatus::query()->create(['donor_id' => $donorId] + $payload);
            }

            foreach ($questions as $question) {
                $questionId = (int) $question->question_id;
                $answer = strtolower(trim((string) ($answers[$questionId] ?? '')));

                if (! in_array($answer, ['yes', 'no'], true)) {
                    continue;
                }

                $followupAnswer = trim((string) ($followups[$questionId] ?? ''));
                $answerPayload = [
                    'answer' => $answer,
                    'followup_answer' => $followupAnswer !== '' ? $followupAnswer : null,
                ];

                $existingAnswer = DonorScreeningAnswer::query()
                    ->where('eligibility_id', $eligibility->eligibility_id)
                    ->where('question_id', $questionId)
                    ->first();

                if ($existingAnswer instanceof DonorScreeningAnswer) {
                    DonorScreeningAnswer::query()
                        ->where('eligibility_id', $eligibility->eligibility_id)
                        ->where('question_id', $questionId)
                        ->update($answerPayload);
                } else {
                    DonorScreeningAnswer::query()->create([
                        'eligibility_id' => $eligibility->eligibility_id,
                        'question_id' => $questionId,
                    ] + $answerPayload);
                }
            }
        });

        $eligibilityId = $eligibility instanceof EligibilityStatus ? (int) $eligibility->eligibility_id : null;
        $this->writeEligibilityAudit($request, $result, $eligibilityId);

        if ($result['status'] === 'for_review' && $eligibilityId !== null) {
            $donorName = trim((string) $context['donor']->first_name . ' ' . (string) $context['donor']->last_name);
            app(AdminNotificationService::class)->createAdminEvent(
                'eligibility_submitted',
                'Eligibility Review Submitted',
                "{$donorName} submitted eligibility answers that require review.",
                'eligibility',
                $eligibilityId
            );
        }

        $this->createDonorNotification(
            $donorId,
            'eligibility_submitted',
            $this->eligibilityResultMessage($result)
        );

        return redirect()
            ->route('donor.check-eligibility')
            ->with('success', $this->eligibilityResultMessage($result));
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

        $alerts = $this->donorNotificationQuery((int) $context['donor']->donor_id)
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

        $alertsCount = $this->donorNotificationQuery((int) $donor->donor_id)
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
            ['key' => 'verification', 'label' => 'Verify Identity', 'href' => route('donor.verification.index')],
            ['key' => 'history', 'label' => 'History', 'href' => route('donor.history')],
            ['key' => 'alerts', 'label' => 'Alerts', 'href' => route('donor.alerts')],
        ];
    }

    private function donorCanBookAppointment(Donor $donor): bool
    {
        return $this->donorVerificationStatus($donor) === 'verified';
    }

    private function donorVerificationStatus(Donor $donor): string
    {
        if (! Schema::hasColumn('donors', 'verification_status')) {
            return 'unverified';
        }

        $status = strtolower(trim((string) ($donor->verification_status ?? 'unverified')));

        return in_array($status, ['unverified', 'pending', 'verified', 'rejected'], true) ? $status : 'unverified';
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

    private function donorNotificationQuery(int $donorId)
    {
        $query = Notification::query()->where('donor_id', $donorId);

        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'recipient_type')) {
            $query->where(function ($builder): void {
                $builder->whereNull('recipient_type')
                    ->orWhereIn('recipient_type', ['donor', 'all_donors']);
            });
        }

        return $query;
    }

    private function activeScreeningQuestions()
    {
        return EligibilityQuestion::query()
            ->where('is_active', true)
            ->orderBy('question_order')
            ->orderBy('question_id')
            ->get();
    }

    private function buildEligibilityStatusPayload(array $result, ?string $latestDonationDate): array
    {
        $payload = [
            'last_donation_date' => $latestDonationDate,
            'next_eligible_date' => $result['next_eligible_date'] ?? null,
            'status' => $result['status'],
        ];

        foreach (['result_reason', 'recommendation_message', 'source'] as $column) {
            if (Schema::hasColumn('eligibility_status', $column)) {
                $payload[$column] = $result[$column] ?? null;
            }
        }

        if (Schema::hasColumn('eligibility_status', 'reviewed_by_admin_id')) {
            $payload['reviewed_by_admin_id'] = null;
        }

        if (Schema::hasColumn('eligibility_status', 'reviewed_at')) {
            $payload['reviewed_at'] = null;
        }

        if (Schema::hasColumn('eligibility_status', 'review_notes')) {
            $payload['review_notes'] = null;
        }

        return $payload;
    }

    private function eligibilityResultMessage(array $result): string
    {
        return match ($result['status'] ?? '') {
            'eligible' => 'You are initially eligible to donate blood. Please proceed to the next step.',
            'temporary_deferred' => 'Your donation is temporarily deferred. Please review your next eligible date and recommendation.',
            'not_eligible' => 'Your answers indicate that you are not eligible to donate blood at this time.',
            'for_review' => 'Your screening submission is pending review by authorized personnel.',
            default => 'Your eligibility screening has been submitted.',
        };
    }

    private function createDonorNotification(int $donorId, string $type, string $message): void
    {
        if ($donorId <= 0 || ! Schema::hasTable('notifications')) {
            return;
        }

        try {
            $payload = [
                'donor_id' => $donorId,
                'message' => $message,
                'notification_type' => $type,
                'is_read' => 0,
                'created_at' => now(),
            ];

            if (Schema::hasColumn('notifications', 'push_sent')) {
                $payload['push_sent'] = 0;
            }

            DB::table('notifications')->insert($payload);
        } catch (Throwable $exception) {
            logger()->warning('Failed to create donor eligibility notification.', [
                'donor_id' => $donorId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function writeEligibilityAudit(Request $request, array $result, ?int $eligibilityId): void
    {
        $description = 'Automatic eligibility result: ' . ($result['status'] ?? 'unknown');

        try {
            $donorName = trim((string) $request->session()->get('donor_name', 'Donor'));
            $metadata = [
                'donor_id' => is_numeric($request->session()->get('donor_id')) ? (int) $request->session()->get('donor_id') : null,
                'status' => $result['status'] ?? null,
                'source' => $result['source'] ?? 'auto',
                'result_reason' => $result['result_reason'] ?? null,
                'matched_question_ids' => array_map(
                    fn (array $match): ?int => isset($match['question_id']) ? (int) $match['question_id'] : null,
                    $result['matched_questions'] ?? []
                ),
            ];

            if (! Schema::hasTable('audit_logs')) {
                logger()->info($description, $metadata);
                return;
            }

            DB::table('audit_logs')->insert([
                'actor_admin_id' => null,
                'actor_name' => $donorName !== '' ? $donorName : 'Donor',
                'actor_role' => 'Donor',
                'action_type' => 'eligibility_auto_evaluated',
                'module_type' => 'eligibility',
                'target_table' => 'eligibility_status',
                'target_id' => $eligibilityId,
                'description' => $description,
                'ip_address' => $request->ip(),
                'result' => 'success',
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Failed to write donor eligibility audit.', [
                'eligibility_id' => $eligibilityId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
