<?php

declare(strict_types=1);

use App\Http\Controllers\EligibilityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$created = [
    'donor_id' => null,
    'auth_id' => null,
    'eligibility_ids' => [],
];

try {
    $bloodTypeId = DB::table('blood_types')->value('blood_type_id')
        ?: DB::table('blood_types')->insertGetId(['blood_type' => 'O+']);

    $locationId = DB::table('locations')->value('location_id')
        ?: DB::table('locations')->insertGetId([
            'street_address' => 'Legacy Check Street',
            'barangay_name' => 'Legacy Check Barangay',
            'city' => 'Legacy Check City',
            'province' => 'Legacy Check Province',
        ]);

    $donorId = (int) DB::table('donors')->insertGetId([
        'first_name' => 'Legacy',
        'last_name' => 'EndpointCheck',
        'gender' => 'male',
        'birthdate' => '1995-01-01',
        'contact_number' => '09990001111',
        'blood_type_id' => $bloodTypeId,
        'location_id' => $locationId,
        'date_registered' => now()->toDateString(),
    ]);
    $created['donor_id'] = $donorId;

    $authId = (int) DB::table('donor_authentication')->insertGetId([
        'donor_id' => $donorId,
        'email' => 'legacy-endpoint-check@example.test',
        'password' => bcrypt('secret123'),
        'is_verified' => 1,
        'verified_at' => now(),
        'created_at' => now(),
    ]);
    $created['auth_id'] = $authId;

    foreach (['Approved', 'Rejected', 'Deferred', 'Pending Review'] as $status) {
        $created['eligibility_ids'][] = (int) DB::table('eligibility_status')->insertGetId([
            'donor_id' => $donorId,
            'status' => $status,
            'source' => null,
            'result_reason' => null,
            'recommendation_message' => null,
            'next_eligible_date' => $status === 'Deferred' ? now()->addDays(7)->toDateString() : null,
        ]);
    }

    $request = Request::create('/admin/eligibility/data', 'GET', [
        'page' => 1,
        'per_page' => 10,
        'search' => 'legacy-endpoint-check@example.test',
    ]);

    $response = app(EligibilityController::class)->data($request);
    assert_true($response->getStatusCode() === 200, 'Endpoint should return 200.');

    $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    $rows = collect($payload['data'] ?? [])->keyBy('raw_status');

    assert_true(($rows['Approved']['status'] ?? null) === 'eligible', 'Approved should normalize to eligible.');
    assert_true(($rows['Approved']['status_label'] ?? null) === 'Eligible', 'Approved label should be Eligible.');
    assert_true(($rows['Rejected']['status'] ?? null) === 'not_eligible', 'Rejected should normalize to not_eligible.');
    assert_true(($rows['Deferred']['status'] ?? null) === 'temporary_deferred', 'Deferred should normalize to temporary_deferred.');
    assert_true(($rows['Pending Review']['status'] ?? null) === 'for_review', 'Pending Review should normalize to for_review.');
    assert_true(($rows['Pending Review']['admin_is_reviewable'] ?? null) === true, 'Only normalized for_review should be reviewable.');
    assert_true(($rows['Approved']['admin_is_reviewable'] ?? null) === false, 'Eligible row should be view only.');
    assert_true(($rows['Approved']['source'] ?? null) === 'legacy', 'Null source should normalize to legacy.');
    assert_true(($rows['Approved']['source_label'] ?? null) === 'Legacy', 'Null source label should be Legacy.');
    assert_true(($rows['Approved']['result_reason'] ?? null) === 'No recorded eligibility reason.', 'Missing reason should use fallback.');
    assert_true(($rows['Approved']['recommendation_message'] ?? null) === 'No recommendation recorded.', 'Missing recommendation should use fallback.');

    echo json_encode([
        'pass' => true,
        'sample_rows' => $payload['data'],
        'stats' => $payload['stats'] ?? [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if (! empty($created['eligibility_ids'])) {
        DB::table('eligibility_status')->whereIn('eligibility_id', $created['eligibility_ids'])->delete();
    }
    if (! empty($created['auth_id'])) {
        DB::table('donor_authentication')->where('auth_id', $created['auth_id'])->delete();
    }
    if (! empty($created['donor_id'])) {
        DB::table('donors')->where('donor_id', $created['donor_id'])->delete();
    }
}
