<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EligibilityPhase3E2ETest extends TestCase
{
    use DatabaseTransactions;

    public function test_phase_3_automatic_eligibility_end_to_end_cases(): void
    {
        if (! Schema::hasTable('screening_questions') || ! Schema::hasTable('donors') || ! Schema::hasTable('eligibility_status')) {
            $this->markTestSkipped('Eligibility tables are not available in the current testing database connection.');
        }

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $bloodTypeId = $this->ensureBloodType();
        $locationId = $this->ensureLocation();
        $donorId = $this->createDonor($bloodTypeId, $locationId);

        $sleepQuestionId = $this->ensureQuestion([
            'question_text' => 'Did you sleep at least 5 to 6 hours last night?',
            'followup_prompt' => 'How many hours did you sleep?',
            'followup_trigger' => 'no',
            'risk_level' => 'temporary_defer',
            'trigger_answer' => 'no',
            'deferral_days' => 1,
            'recommendation_message' => 'Please get enough rest before donating blood.',
            'question_order' => 901,
        ]);

        $antibioticQuestionId = $this->ensureQuestion([
            'question_text' => 'Are you currently taking an antibiotic?',
            'followup_prompt' => 'What antibiotic are you taking?',
            'followup_trigger' => 'yes',
            'risk_level' => 'for_review',
            'trigger_answer' => 'yes',
            'deferral_days' => null,
            'recommendation_message' => 'Your answer requires review by authorized personnel.',
            'question_order' => 902,
        ]);

        $hivQuestionId = $this->ensureQuestion([
            'question_text' => 'In the past 2 years, have you received any medication by injection to prevent HIV infection (i.e. PrEP or PEP)?',
            'followup_prompt' => 'When is the last time you were injected?',
            'followup_trigger' => 'yes',
            'risk_level' => 'auto_reject',
            'trigger_answer' => 'yes',
            'deferral_days' => null,
            'recommendation_message' => 'This response indicates high risk for donation at this time.',
            'question_order' => 903,
        ]);

        $aspirinQuestionId = $this->ensureQuestion([
            'question_text' => 'In the past 48 hours, have you taken aspirin or anything that has aspirin in it?',
            'followup_prompt' => 'Please indicate when you took aspirin.',
            'followup_trigger' => 'yes',
            'risk_level' => 'temporary_defer',
            'trigger_answer' => 'yes',
            'deferral_days' => 2,
            'recommendation_message' => 'Please wait for aspirin washout before donation.',
            'question_order' => 904,
        ]);

        $allQuestions = $this->activeQuestions();
        $baseAnswers = $this->buildNonTriggerAnswers($allQuestions);

        // 1) All safe answers => eligible
        $case1 = $this->submitCase($donorId, $baseAnswers);
        $this->assertSame('eligible', $case1['status']);
        $this->assertNull($case1['next_eligible_date']);
        $this->assertReviewableState($donorId, false);

        // 2) HIV treatment yes => not_eligible
        $case2Answers = $baseAnswers;
        $case2Answers[$hivQuestionId] = 'yes';
        $case2 = $this->submitCase($donorId, $case2Answers);
        $this->assertSame('not_eligible', $case2['status']);
        $this->assertNull($case2['next_eligible_date']);
        $this->assertReviewableState($donorId, false);

        // 3) Sleep less than required => temporary_deferred
        $case3Answers = $baseAnswers;
        $case3Answers[$sleepQuestionId] = 'no';
        $case3 = $this->submitCase($donorId, $case3Answers);
        $this->assertSame('temporary_deferred', $case3['status']);
        $this->assertNotNull($case3['next_eligible_date']);
        $this->assertReviewableState($donorId, false);

        // 4) Antibiotic yes => for_review
        $case4Answers = $baseAnswers;
        $case4Answers[$antibioticQuestionId] = 'yes';
        $case4 = $this->submitCase($donorId, $case4Answers);
        $this->assertSame('for_review', $case4['status']);
        $this->assertNull($case4['next_eligible_date']);
        $this->assertReviewableState($donorId, true);

        // 5) HIV yes + aspirin yes => not_eligible (priority wins)
        $case5Answers = $baseAnswers;
        $case5Answers[$hivQuestionId] = 'yes';
        $case5Answers[$aspirinQuestionId] = 'yes';
        $case5 = $this->submitCase($donorId, $case5Answers);
        $this->assertSame('not_eligible', $case5['status']);
        $this->assertNull($case5['next_eligible_date']);
        $this->assertReviewableState($donorId, false);
    }

    private function submitCase(int $donorId, array $answers): array
    {
        $response = $this
            ->withSession([
                'donor_id' => $donorId,
                'donor_name' => 'Phase3 Donor',
            ])
            ->post(route('donor.check-eligibility.submit'), [
                'answers' => $answers,
                'followups' => [],
            ]);

        $response->assertRedirect(route('donor.check-eligibility'));

        $row = DB::table('eligibility_status')
            ->where('donor_id', $donorId)
            ->orderByDesc('eligibility_id')
            ->first();

        $this->assertNotNull($row);

        $status = strtolower(trim((string) ($row->status ?? '')));
        if ($status === 'approved') {
            $status = 'eligible';
        }
        if ($status === 'declined') {
            $status = 'not_eligible';
        }

        if (Schema::hasColumn('eligibility_status', 'result_reason')) {
            $this->assertNotSame('', trim((string) ($row->result_reason ?? '')));
        }

        if (Schema::hasColumn('eligibility_status', 'recommendation_message')) {
            $this->assertNotSame('', trim((string) ($row->recommendation_message ?? '')));
        }

        if (Schema::hasColumn('eligibility_status', 'source')) {
            $this->assertSame('auto', strtolower(trim((string) ($row->source ?? ''))));
        }

        return [
            'status' => $status,
            'next_eligible_date' => $row->next_eligible_date ?? null,
        ];
    }

    private function assertReviewableState(int $donorId, bool $expectedReviewable): void
    {
        $response = $this->getJson(route('admin.eligibility.data', ['per_page' => 200]));
        $response->assertOk();
        $payload = $response->json('data');
        $this->assertIsArray($payload);

        $row = collect($payload)->firstWhere('donor_id', $donorId);
        $this->assertNotNull($row);
        $this->assertSame($expectedReviewable, (bool) ($row['is_reviewable'] ?? false));
    }

    private function activeQuestions(): Collection
    {
        return DB::table('screening_questions')
            ->where('is_active', 1)
            ->orderBy('question_order')
            ->orderBy('question_id')
            ->get();
    }

    private function buildNonTriggerAnswers(Collection $questions): array
    {
        $answers = [];

        foreach ($questions as $question) {
            $riskLevel = strtolower(trim((string) ($question->risk_level ?? 'safe')));
            $trigger = strtolower(trim((string) ($question->trigger_answer ?? '')));
            $answer = 'no';

            if (in_array($riskLevel, ['auto_reject', 'temporary_defer', 'for_review'], true) && in_array($trigger, ['yes', 'no'], true)) {
                $answer = $trigger === 'yes' ? 'no' : 'yes';
            }

            $answers[(int) $question->question_id] = $answer;
        }

        return $answers;
    }

    private function ensureQuestion(array $attributes): int
    {
        $existing = DB::table('screening_questions')
            ->where('question_text', $attributes['question_text'])
            ->first();

        $payload = [
            'followup_prompt' => $attributes['followup_prompt'],
            'followup_trigger' => $attributes['followup_trigger'],
            'risk_level' => $attributes['risk_level'],
            'trigger_answer' => $attributes['trigger_answer'],
            'deferral_days' => $attributes['deferral_days'],
            'recommendation_message' => $attributes['recommendation_message'],
            'question_order' => $attributes['question_order'],
            'is_active' => 1,
        ];

        if ($existing) {
            DB::table('screening_questions')
                ->where('question_id', $existing->question_id)
                ->update($payload);

            return (int) $existing->question_id;
        }

        return (int) DB::table('screening_questions')->insertGetId([
            'question_text' => $attributes['question_text'],
        ] + $payload);
    }

    private function ensureBloodType(): int
    {
        $existing = DB::table('blood_types')->orderBy('blood_type_id')->first();
        if ($existing) {
            return (int) $existing->blood_type_id;
        }

        return (int) DB::table('blood_types')->insertGetId(['blood_type' => 'O+']);
    }

    private function ensureLocation(): int
    {
        $existing = DB::table('locations')->orderBy('location_id')->first();
        if ($existing) {
            return (int) $existing->location_id;
        }

        return (int) DB::table('locations')->insertGetId([
            'street_address' => 'Test Street 1',
            'barangay_name' => 'Test Barangay',
            'city' => 'Test City',
            'province' => 'Test Province',
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    private function createDonor(int $bloodTypeId, int $locationId): int
    {
        return (int) DB::table('donors')->insertGetId([
            'first_name' => 'Phase3',
            'last_name' => 'Donor',
            'gender' => 'male',
            'birthdate' => '1995-01-01',
            'contact_number' => '09171234567',
            'blood_type_id' => $bloodTypeId,
            'location_id' => $locationId,
            'date_registered' => now()->toDateString(),
        ]);
    }
}
