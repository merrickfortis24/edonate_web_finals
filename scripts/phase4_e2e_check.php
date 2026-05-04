<?php

declare(strict_types=1);

use App\Http\Controllers\EligibilityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function expect_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function admin_session_payload(): array
{
    $admin = DB::table('admins')->select('admin_id', 'full_name', 'role')->orderBy('admin_id')->first();
    if ($admin) {
        return [
            'admin_id' => (int) $admin->admin_id,
            'admin_role' => (string) ($admin->role ?: 'admin'),
            'admin_full_name' => (string) ($admin->full_name ?: 'QA Admin'),
        ];
    }

    return [
        'admin_id' => 1,
        'admin_role' => 'admin',
        'admin_full_name' => 'QA Admin',
    ];
}

function make_admin_request(string $uri, string $method, array $data, array $sessionData): Request
{
    $request = Request::create($uri, $method, $data);
    $session = app('session')->driver();
    $session->start();
    foreach ($sessionData as $key => $value) {
        $session->put($key, $value);
    }
    $request->setLaravelSession($session);
    return $request;
}

function review_record(EligibilityController $controller, int $eligibilityId, array $payload, array $sessionData): array
{
    $request = make_admin_request("/admin/eligibility/{$eligibilityId}/review", 'PATCH', $payload, $sessionData);
    $response = $controller->review($request, $eligibilityId);
    return [
        'status_code' => $response->getStatusCode(),
        'payload' => json_decode((string) $response->getContent(), true),
    ];
}

$created = [
    'question_ids' => [],
    'donor_id' => null,
    'auth_id' => null,
    'eligibility_ids' => [],
];

try {
    $bloodTypeId = DB::table('blood_types')->value('blood_type_id');
    if (! $bloodTypeId) {
        $bloodTypeId = DB::table('blood_types')->insertGetId(['blood_type' => 'O+']);
    }

    $locationId = DB::table('locations')->value('location_id');
    if (! $locationId) {
        $locationId = DB::table('locations')->insertGetId([
            'street_address' => 'Phase4 Street',
            'barangay_name' => 'Phase4 Barangay',
            'city' => 'Phase4 City',
            'province' => 'Phase4 Province',
        ]);
    }

    $questionId = (int) DB::table('screening_questions')->insertGetId([
        'question_text' => '[PHASE4 TEST] Are you currently taking an antibiotic?',
        'followup_prompt' => 'What antibiotic are you taking?',
        'followup_trigger' => 'yes',
        'risk_level' => 'for_review',
        'trigger_answer' => 'yes',
        'deferral_days' => null,
        'recommendation_message' => 'Your answer requires review by authorized personnel.',
        'question_order' => 9901,
        'is_active' => 1,
    ]);
    $created['question_ids'][] = $questionId;

    $donorId = (int) DB::table('donors')->insertGetId([
        'first_name' => 'Phase4',
        'last_name' => 'E2E',
        'gender' => 'male',
        'birthdate' => '1995-01-01',
        'contact_number' => '09991112222',
        'blood_type_id' => $bloodTypeId,
        'location_id' => $locationId,
        'date_registered' => now()->toDateString(),
    ]);
    $created['donor_id'] = $donorId;

    $authId = (int) DB::table('donor_authentication')->insertGetId([
        'donor_id' => $donorId,
        'email' => 'phase4-e2e@example.test',
        'password' => bcrypt('secret123'),
        'is_verified' => 1,
        'verification_token' => null,
        'verification_sent_at' => null,
        'verified_at' => now(),
        'created_at' => now(),
    ]);
    $created['auth_id'] = $authId;

    $eligibleId = (int) DB::table('eligibility_status')->insertGetId([
        'donor_id' => $donorId,
        'status' => 'eligible',
        'result_reason' => 'Auto pass',
        'recommendation_message' => 'Proceed to next step.',
        'next_eligible_date' => null,
        'source' => 'auto',
    ]);
    $notEligibleId = (int) DB::table('eligibility_status')->insertGetId([
        'donor_id' => $donorId,
        'status' => 'not_eligible',
        'result_reason' => 'Auto rejection',
        'recommendation_message' => 'Donation not allowed now.',
        'next_eligible_date' => null,
        'source' => 'auto',
    ]);
    $deferredId = (int) DB::table('eligibility_status')->insertGetId([
        'donor_id' => $donorId,
        'status' => 'temporary_deferred',
        'result_reason' => 'Auto temporary defer',
        'recommendation_message' => 'Wait and return later.',
        'next_eligible_date' => now()->addDays(7)->toDateString(),
        'source' => 'auto',
    ]);
    $forReviewViewId = (int) DB::table('eligibility_status')->insertGetId([
        'donor_id' => $donorId,
        'status' => 'for_review',
        'result_reason' => 'Needs manual review.',
        'recommendation_message' => 'Please verify details.',
        'next_eligible_date' => null,
        'source' => 'auto',
    ]);
    $forReviewApproveId = (int) DB::table('eligibility_status')->insertGetId([
        'donor_id' => $donorId,
        'status' => 'for_review',
        'result_reason' => 'Needs manual review.',
        'recommendation_message' => 'Please verify details.',
        'next_eligible_date' => null,
        'source' => 'auto',
    ]);
    $forReviewRejectId = (int) DB::table('eligibility_status')->insertGetId([
        'donor_id' => $donorId,
        'status' => 'for_review',
        'result_reason' => 'Needs manual review.',
        'recommendation_message' => 'Please verify details.',
        'next_eligible_date' => null,
        'source' => 'auto',
    ]);
    $forReviewDeferId = (int) DB::table('eligibility_status')->insertGetId([
        'donor_id' => $donorId,
        'status' => 'for_review',
        'result_reason' => 'Needs manual review.',
        'recommendation_message' => 'Please verify details.',
        'next_eligible_date' => null,
        'source' => 'auto',
    ]);
    $created['eligibility_ids'] = [$eligibleId, $notEligibleId, $deferredId, $forReviewViewId, $forReviewApproveId, $forReviewRejectId, $forReviewDeferId];

    foreach ($created['eligibility_ids'] as $eid) {
        DB::table('donor_screening_answers')->insert([
            'eligibility_id' => $eid,
            'question_id' => $questionId,
            'answer' => 'yes',
            'followup_answer' => 'Amoxicillin',
        ]);
    }

    $sessionData = admin_session_payload();
    $controller = app(EligibilityController::class);

    $dataRequest = make_admin_request('/admin/eligibility/data', 'GET', [
        'page' => 1,
        'per_page' => 100,
        'search' => 'phase4-e2e@example.test',
    ], $sessionData);
    $dataResponse = $controller->data($dataRequest);
    expect_true($dataResponse->getStatusCode() === 200, 'Data endpoint should return 200.');
    $dataPayload = json_decode((string) $dataResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
    $rows = collect($dataPayload['data'] ?? [])->keyBy('eligibility_id');

    expect_true(($rows[$eligibleId]['admin_is_reviewable'] ?? null) === false, 'eligible should be view-only.');
    expect_true(($rows[$notEligibleId]['admin_is_reviewable'] ?? null) === false, 'not_eligible should be view-only.');
    expect_true(($rows[$deferredId]['admin_is_reviewable'] ?? null) === false, 'temporary_deferred should be view-only.');
    expect_true(($rows[$forReviewViewId]['admin_is_reviewable'] ?? null) === true, 'for_review should be reviewable.');

    $detailRequest = make_admin_request("/admin/eligibility/{$forReviewViewId}", 'GET', [], $sessionData);
    $detailResponse = $controller->show($detailRequest, $forReviewViewId);
    expect_true($detailResponse->getStatusCode() === 200, 'Detail endpoint should return 200.');
    $detailPayload = json_decode((string) $detailResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
    expect_true(! empty($detailPayload['answers']), 'Review modal data should include donor answers.');
    expect_true(! empty($detailPayload['triggered_risk_questions']), 'Review modal data should include triggered risk questions.');

    $approve = review_record($controller, $forReviewApproveId, [
        'status' => 'eligible',
        'review_notes' => 'Approved after manual check.',
    ], $sessionData);
    expect_true($approve['status_code'] === 200, 'Approve for_review should succeed.');

    $reject = review_record($controller, $forReviewRejectId, [
        'status' => 'not_eligible',
        'review_notes' => 'Rejected after manual check.',
    ], $sessionData);
    expect_true($reject['status_code'] === 200, 'Reject for_review should succeed.');

    $deferDate = now()->addDays(10)->toDateString();
    $defer = review_record($controller, $forReviewDeferId, [
        'status' => 'temporary_deferred',
        'next_eligible_date' => $deferDate,
        'review_notes' => 'Deferred after manual check.',
    ], $sessionData);
    expect_true($defer['status_code'] === 200, 'Defer for_review should succeed.');

    $autoBlock = review_record($controller, $eligibleId, [
        'status' => 'not_eligible',
        'review_notes' => 'Should fail on auto decision.',
    ], $sessionData);
    expect_true($autoBlock['status_code'] === 422, 'Auto-decided record should not be manually reviewed.');

    $approvedRow = DB::table('eligibility_status')->where('eligibility_id', $forReviewApproveId)->first();
    $rejectedRow = DB::table('eligibility_status')->where('eligibility_id', $forReviewRejectId)->first();
    $deferredRow = DB::table('eligibility_status')->where('eligibility_id', $forReviewDeferId)->first();

    expect_true((string) $approvedRow->source === 'admin_review', 'source should change to admin_review after approve.');
    expect_true((string) $rejectedRow->source === 'admin_review', 'source should change to admin_review after reject.');
    expect_true((string) $deferredRow->source === 'admin_review', 'source should change to admin_review after defer.');

    expect_true(! empty($approvedRow->reviewed_by_admin_id), 'reviewed_by_admin_id should be saved on approve.');
    expect_true(! empty($rejectedRow->reviewed_by_admin_id), 'reviewed_by_admin_id should be saved on reject.');
    expect_true(! empty($deferredRow->reviewed_by_admin_id), 'reviewed_by_admin_id should be saved on defer.');

    expect_true(! empty($approvedRow->reviewed_at), 'reviewed_at should be saved on approve.');
    expect_true(! empty($rejectedRow->reviewed_at), 'reviewed_at should be saved on reject.');
    expect_true(! empty($deferredRow->reviewed_at), 'reviewed_at should be saved on defer.');

    expect_true((string) $approvedRow->review_notes === 'Approved after manual check.', 'review_notes should be saved on approve.');
    expect_true((string) $rejectedRow->review_notes === 'Rejected after manual check.', 'review_notes should be saved on reject.');
    expect_true((string) $deferredRow->review_notes === 'Deferred after manual check.', 'review_notes should be saved on defer.');
    expect_true((string) $deferredRow->next_eligible_date === $deferDate, 'next_eligible_date should be saved for defer.');

    echo json_encode([
        'pass' => true,
        'summary' => [
            'button_logic' => [
                'eligible' => 'view_only',
                'not_eligible' => 'view_only',
                'temporary_deferred' => 'view_only',
                'for_review' => 'review',
            ],
            'review_modal' => [
                'answers_loaded' => true,
                'triggered_risk_questions_loaded' => true,
            ],
            'manual_decisions' => [
                'approve_for_review' => 'ok',
                'reject_for_review' => 'ok',
                'defer_for_review' => 'ok',
                'auto_record_review_blocked' => 'ok',
            ],
            'post_review_fields' => [
                'source_admin_review' => true,
                'reviewed_by_admin_id_saved' => true,
                'reviewed_at_saved' => true,
                'review_notes_saved' => true,
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if (! empty($created['eligibility_ids'])) {
        DB::table('donor_screening_answers')->whereIn('eligibility_id', $created['eligibility_ids'])->delete();
        DB::table('eligibility_status')->whereIn('eligibility_id', $created['eligibility_ids'])->delete();
        DB::table('audit_logs')->where('description', 'like', '%EL%')->where('action_type', 'like', 'eligibility_%')->delete();
    }
    if (! empty($created['auth_id'])) {
        DB::table('donor_authentication')->where('auth_id', $created['auth_id'])->delete();
    }
    if (! empty($created['donor_id'])) {
        DB::table('notifications')->where('donor_id', $created['donor_id'])->delete();
        DB::table('donors')->where('donor_id', $created['donor_id'])->delete();
    }
    if (! empty($created['question_ids'])) {
        DB::table('screening_questions')->whereIn('question_id', $created['question_ids'])->delete();
    }
}

