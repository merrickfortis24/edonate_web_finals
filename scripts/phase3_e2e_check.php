<?php

declare(strict_types=1);

use App\Http\Controllers\DonorPortalController;
use App\Http\Controllers\EligibilityController;
use App\Services\EligibilityEvaluator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$requiredTables = ['screening_questions', 'eligibility_status', 'donor_screening_answers', 'donors', 'blood_types', 'locations'];
foreach ($requiredTables as $table) {
    if (!Schema::hasTable($table)) {
        fwrite(STDERR, "Missing required table: {$table}\n");
        exit(1);
    }
}

$createdQuestionIds = [];
$createdDonorId = null;
$createdEligibilityIds = [];

try {
    $bloodTypeId = ensureBloodType();
    $locationId = ensureLocation();
    $createdDonorId = createDonor($bloodTypeId, $locationId);

    $sleepQuestionId = ensureQuestion([
        'question_text' => '[PHASE3 TEST] Did you sleep at least 5 to 6 hours last night?',
        'followup_prompt' => 'How many hours did you sleep?',
        'followup_trigger' => 'no',
        'risk_level' => 'temporary_defer',
        'trigger_answer' => 'no',
        'deferral_days' => 1,
        'recommendation_message' => 'Please get enough rest before donating blood.',
        'question_order' => 9501,
    ], $createdQuestionIds);

    $antibioticQuestionId = ensureQuestion([
        'question_text' => '[PHASE3 TEST] Are you currently taking an antibiotic?',
        'followup_prompt' => 'What antibiotic are you taking?',
        'followup_trigger' => 'yes',
        'risk_level' => 'for_review',
        'trigger_answer' => 'yes',
        'deferral_days' => null,
        'recommendation_message' => 'Your answer requires review by authorized personnel.',
        'question_order' => 9502,
    ], $createdQuestionIds);

    $hivQuestionId = ensureQuestion([
        'question_text' => '[PHASE3 TEST] In the past 2 years, have you received any medication by injection to prevent HIV infection (i.e. PrEP or PEP)?',
        'followup_prompt' => 'When is the last time you were injected?',
        'followup_trigger' => 'yes',
        'risk_level' => 'auto_reject',
        'trigger_answer' => 'yes',
        'deferral_days' => null,
        'recommendation_message' => 'This response indicates high risk for donation at this time.',
        'question_order' => 9503,
    ], $createdQuestionIds);

    $aspirinQuestionId = ensureQuestion([
        'question_text' => '[PHASE3 TEST] In the past 48 hours, have you taken aspirin or anything that has aspirin in it?',
        'followup_prompt' => 'Please indicate when you took aspirin.',
        'followup_trigger' => 'yes',
        'risk_level' => 'temporary_defer',
        'trigger_answer' => 'yes',
        'deferral_days' => 2,
        'recommendation_message' => 'Please wait for aspirin washout before donation.',
        'question_order' => 9504,
    ], $createdQuestionIds);

    $questions = activeQuestions();
    $baseAnswers = buildNonTriggerAnswers($questions);

    $cases = [
        '1_all_safe' => $baseAnswers,
        '2_hiv_yes' => array_replace($baseAnswers, [$hivQuestionId => 'yes']),
        '3_sleep_low' => array_replace($baseAnswers, [$sleepQuestionId => 'no']),
        '4_antibiotic_yes' => array_replace($baseAnswers, [$antibioticQuestionId => 'yes']),
        '5_hiv_plus_aspirin' => array_replace($baseAnswers, [$hivQuestionId => 'yes', $aspirinQuestionId => 'yes']),
    ];

    $results = [];
    foreach ($cases as $name => $answers) {
        $results[$name] = runCase($createdDonorId, $answers);
        $eligibilityId = $results[$name]['eligibility_id'];
        if ($eligibilityId > 0) {
            $createdEligibilityIds[$eligibilityId] = true;
        }
    }

    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if ($createdDonorId !== null) {
        $eligibilityIds = DB::table('eligibility_status')->where('donor_id', $createdDonorId)->pluck('eligibility_id')->all();
        if ($eligibilityIds !== []) {
            DB::table('donor_screening_answers')->whereIn('eligibility_id', $eligibilityIds)->delete();
            DB::table('eligibility_status')->whereIn('eligibility_id', $eligibilityIds)->delete();
        }
        DB::table('notifications')->where('donor_id', $createdDonorId)->delete();
        DB::table('donors')->where('donor_id', $createdDonorId)->delete();
    }

    if ($createdQuestionIds !== []) {
        DB::table('screening_questions')->whereIn('question_id', $createdQuestionIds)->delete();
    }
}

function runCase(int $donorId, array $answers): array
{
    $request = Request::create('/eligibility', 'POST', [
        'answers' => $answers,
        'followups' => [],
    ]);

    $session = app('session')->driver();
    $session->start();
    $session->put('donor_id', $donorId);
    $session->put('donor_name', 'Phase3 Test Donor');
    $request->setLaravelSession($session);

    /** @var DonorPortalController $donorController */
    $donorController = app(DonorPortalController::class);
    /** @var EligibilityEvaluator $evaluator */
    $evaluator = app(EligibilityEvaluator::class);
    $donorController->submitEligibility($request, $evaluator);

    $eligibility = DB::table('eligibility_status')
        ->where('donor_id', $donorId)
        ->orderByDesc('eligibility_id')
        ->first();

    if (! $eligibility) {
        throw new RuntimeException('Eligibility row was not created.');
    }

    $adminRequest = Request::create('/admin/eligibility/data', 'GET', [
        'page' => 1,
        'per_page' => 100,
        'search' => 'Phase3 Simulation',
    ]);
    $adminSession = app('session')->driver();
    $adminSession->start();
    $adminSession->put('admin_id', 1);
    $adminSession->put('admin_role', 'admin');
    $adminSession->put('admin_full_name', 'Phase3 Admin');
    $adminRequest->setLaravelSession($adminSession);

    /** @var EligibilityController $eligibilityController */
    $eligibilityController = app(EligibilityController::class);
    $response = $eligibilityController->data($adminRequest);
    $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    $row = null;
    foreach (($payload['data'] ?? []) as $item) {
        if ((int) ($item['donor_id'] ?? 0) === $donorId) {
            $row = $item;
            break;
        }
    }

    if (! is_array($row)) {
        throw new RuntimeException('Donor row missing from admin eligibility data payload.');
    }

    return [
        'eligibility_id' => (int) $eligibility->eligibility_id,
        'status' => strtolower(trim((string) $eligibility->status)),
        'result_reason' => (string) ($eligibility->result_reason ?? ''),
        'recommendation_message' => (string) ($eligibility->recommendation_message ?? ''),
        'next_eligible_date' => $eligibility->next_eligible_date ? (string) $eligibility->next_eligible_date : null,
        'source' => (string) ($eligibility->source ?? ''),
        'admin_is_reviewable' => (bool) ($row['is_reviewable'] ?? false),
    ];
}

function activeQuestions()
{
    return DB::table('screening_questions')
        ->where('is_active', 1)
        ->orderBy('question_order')
        ->orderBy('question_id')
        ->get();
}

function buildNonTriggerAnswers($questions): array
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

function ensureQuestion(array $attributes, array &$createdQuestionIds): int
{
    $existing = DB::table('screening_questions')->where('question_text', $attributes['question_text'])->first();
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
        DB::table('screening_questions')->where('question_id', $existing->question_id)->update($payload);
        return (int) $existing->question_id;
    }

    $id = (int) DB::table('screening_questions')->insertGetId([
        'question_text' => $attributes['question_text'],
    ] + $payload);
    $createdQuestionIds[] = $id;
    return $id;
}

function ensureBloodType(): int
{
    $existing = DB::table('blood_types')->orderBy('blood_type_id')->first();
    if ($existing) {
        return (int) $existing->blood_type_id;
    }

    return (int) DB::table('blood_types')->insertGetId(['blood_type' => 'O+']);
}

function ensureLocation(): int
{
    $existing = DB::table('locations')->orderBy('location_id')->first();
    if ($existing) {
        return (int) $existing->location_id;
    }

    return (int) DB::table('locations')->insertGetId([
        'street_address' => 'Phase3 Test Street',
        'barangay_name' => 'Phase3 Barangay',
        'city' => 'Phase3 City',
        'province' => 'Phase3 Province',
        'latitude' => null,
        'longitude' => null,
    ]);
}

function createDonor(int $bloodTypeId, int $locationId): int
{
    return (int) DB::table('donors')->insertGetId([
        'first_name' => 'Phase3',
        'last_name' => 'Simulation',
        'gender' => 'male',
        'birthdate' => '1995-01-01',
        'contact_number' => '09990001111',
        'blood_type_id' => $bloodTypeId,
        'location_id' => $locationId,
        'date_registered' => now()->toDateString(),
    ]);
}
