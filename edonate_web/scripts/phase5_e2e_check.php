<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DonorVerificationController as AdminDonorVerificationController;
use App\Http\Controllers\DonorPortalController;
use App\Http\Controllers\DonorVerificationController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function expect_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function make_session_request(string $uri, string $method, array $data, array $files, array $sessionData): Request
{
    $request = Request::create($uri, $method, $data, [], $files);
    $session = app('session')->driver();
    $session->start();
    foreach ($sessionData as $key => $value) {
        $session->put($key, $value);
    }
    $request->setLaravelSession($session);

    return $request;
}

function make_upload(string $name = 'identity.pdf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'edonate-id-');
    file_put_contents($path, "%PDF-1.4\n% eDonate verification test\n");

    return new UploadedFile($path, $name, 'application/pdf', null, true);
}

function create_test_donor(string $email, string $status = 'unverified'): int
{
    $bloodTypeId = DB::table('blood_types')->value('blood_type_id')
        ?: DB::table('blood_types')->insertGetId(['blood_type' => 'O+']);

    $locationId = DB::table('locations')->value('location_id')
        ?: DB::table('locations')->insertGetId([
            'street_address' => 'Phase5 Street',
            'barangay_name' => 'Phase5 Barangay',
            'city' => 'Phase5 City',
            'province' => 'Phase5 Province',
        ]);

    $donorId = (int) DB::table('donors')->insertGetId([
        'first_name' => 'Phase5',
        'last_name' => 'Verifier',
        'gender' => 'male',
        'birthdate' => '1995-01-01',
        'contact_number' => '09995550000',
        'blood_type_id' => $bloodTypeId,
        'location_id' => $locationId,
        'date_registered' => now()->toDateString(),
        'verification_status' => $status,
    ]);

    DB::table('donor_authentication')->insert([
        'donor_id' => $donorId,
        'email' => $email,
        'password' => bcrypt('secret123'),
        'is_verified' => 1,
        'verified_at' => now(),
        'created_at' => now(),
    ]);

    DB::table('eligibility_status')->insert([
        'donor_id' => $donorId,
        'status' => 'eligible',
        'result_reason' => 'Phase 5 test eligible',
        'recommendation_message' => 'Proceed to identity verification.',
        'source' => 'auto',
    ]);

    return $donorId;
}

function donor_session(int $donorId, string $email): array
{
    return [
        'donor_id' => $donorId,
        'donor_email' => $email,
        'donor_name' => 'Phase5 Verifier',
    ];
}

function admin_session(): array
{
    $admin = DB::table('admins')->select('admin_id', 'full_name', 'username', 'role')->orderBy('admin_id')->first();

    return [
        'admin_id' => $admin ? (int) $admin->admin_id : 1,
        'admin_full_name' => $admin ? (string) ($admin->full_name ?: $admin->username ?: 'Admin') : 'Admin',
        'admin_username' => $admin ? (string) ($admin->username ?: 'admin') : 'admin',
        'admin_role' => $admin ? (string) ($admin->role ?: 'admin') : 'admin',
    ];
}

function available_booking_slot(): array
{
    $timeSlots = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00'];

    for ($day = 20; $day <= 90; $day++) {
        $date = Carbon::today()->addDays($day)->toDateString();
        foreach ($timeSlots as $slot) {
            $exists = DB::table('appointments')
                ->where('appointment_date', $date)
                ->where('appointment_time', $slot)
                ->exists();

            if (! $exists) {
                return [$date, $slot];
            }
        }
    }

    throw new RuntimeException('No available appointment slot found for Phase 5 test.');
}

function submit_verification(int $donorId, string $email): int
{
    $request = make_session_request('/verification', 'POST', [
        'document_type' => 'national_id',
    ], [
        'document' => make_upload(),
    ], donor_session($donorId, $email));

    app(DonorVerificationController::class)->store($request);

    return (int) DB::table('donor_verifications')
        ->where('donor_id', $donorId)
        ->orderByDesc('verification_id')
        ->value('verification_id');
}

$created = [
    'donor_ids' => [],
    'verification_ids' => [],
    'appointment_ids' => [],
    'document_paths' => [],
];

try {
    $submitEmail = 'phase5-submit@example.test';
    $submitDonorId = create_test_donor($submitEmail);
    $created['donor_ids'][] = $submitDonorId;
    $pendingVerificationId = submit_verification($submitDonorId, $submitEmail);
    $created['verification_ids'][] = $pendingVerificationId;

    $pendingRow = DB::table('donor_verifications')->where('verification_id', $pendingVerificationId)->first();
    expect_true($pendingRow && $pendingRow->status === 'pending', 'Upload should create pending donor_verifications row.');
    expect_true((string) DB::table('donors')->where('donor_id', $submitDonorId)->value('verification_status') === 'pending', 'Upload should set donor verification_status to pending.');
    expect_true(Storage::disk('local')->exists($pendingRow->document_path), 'Uploaded document should exist on private local disk.');
    $created['document_paths'][] = $pendingRow->document_path;

    $documentResponse = app(AdminDonorVerificationController::class)->document(App\Models\DonorVerification::query()->findOrFail($pendingVerificationId));
    expect_true($documentResponse->getStatusCode() === 200, 'Admin document view should return 200.');

    $adminController = app(AdminDonorVerificationController::class);
    $approveRequest = make_session_request('/admin/donor-verifications/' . $pendingVerificationId . '/approve', 'PATCH', [], [], admin_session());
    $adminController->approve($approveRequest, App\Models\DonorVerification::query()->findOrFail($pendingVerificationId));
    $approvedRow = DB::table('donor_verifications')->where('verification_id', $pendingVerificationId)->first();
    expect_true($approvedRow->status === 'verified', 'Approve should mark verification as verified.');
    expect_true((string) DB::table('donors')->where('donor_id', $submitDonorId)->value('verification_status') === 'verified', 'Approve should set donor verification_status to verified.');
    expect_true(! empty($approvedRow->reviewed_by_admin_id), 'Approve should save reviewed_by_admin_id.');
    expect_true(! empty($approvedRow->reviewed_at), 'Approve should save reviewed_at.');

    $rejectEmail = 'phase5-reject@example.test';
    $rejectDonorId = create_test_donor($rejectEmail);
    $created['donor_ids'][] = $rejectDonorId;
    $rejectVerificationId = submit_verification($rejectDonorId, $rejectEmail);
    $created['verification_ids'][] = $rejectVerificationId;
    $created['document_paths'][] = (string) DB::table('donor_verifications')->where('verification_id', $rejectVerificationId)->value('document_path');

    $rejectRequest = make_session_request('/admin/donor-verifications/' . $rejectVerificationId . '/reject', 'PATCH', [
        'rejection_reason' => 'Document is unclear.',
    ], [], admin_session());
    $adminController->reject($rejectRequest, App\Models\DonorVerification::query()->findOrFail($rejectVerificationId));
    $rejectedRow = DB::table('donor_verifications')->where('verification_id', $rejectVerificationId)->first();
    expect_true($rejectedRow->status === 'rejected', 'Reject should mark verification as rejected.');
    expect_true((string) $rejectedRow->rejection_reason === 'Document is unclear.', 'Reject should save rejection reason.');
    expect_true((string) DB::table('donors')->where('donor_id', $rejectDonorId)->value('verification_status') === 'rejected', 'Reject should set donor verification_status to rejected.');

    $reuploadVerificationId = submit_verification($rejectDonorId, $rejectEmail);
    $created['verification_ids'][] = $reuploadVerificationId;
    $created['document_paths'][] = (string) DB::table('donor_verifications')->where('verification_id', $reuploadVerificationId)->value('document_path');
    expect_true((string) DB::table('donors')->where('donor_id', $rejectDonorId)->value('verification_status') === 'pending', 'Re-upload after rejection should return donor status to pending.');

    $blockedEmail = 'phase5-blocked-book@example.test';
    $blockedDonorId = create_test_donor($blockedEmail, 'unverified');
    $created['donor_ids'][] = $blockedDonorId;
    [$blockedDate, $blockedTime] = available_booking_slot();
    $blockedRequest = make_session_request('/appointments/book', 'POST', [
        'donation_date' => $blockedDate,
        'time_slot' => $blockedTime,
        'donation_center' => 'Phase5 Blood Bank',
    ], [], donor_session($blockedDonorId, $blockedEmail));
    app(DonorPortalController::class)->storeAppointment($blockedRequest);
    expect_true(! DB::table('appointments')->where('donor_id', $blockedDonorId)->exists(), 'Unverified donor should not create appointment.');

    [$verifiedDate, $verifiedTime] = available_booking_slot();
    $verifiedRequest = make_session_request('/appointments/book', 'POST', [
        'donation_date' => $verifiedDate,
        'time_slot' => $verifiedTime,
        'donation_center' => 'Phase5 Blood Bank',
    ], [], donor_session($submitDonorId, $submitEmail));
    app(DonorPortalController::class)->storeAppointment($verifiedRequest);
    $appointmentId = DB::table('appointments')->where('donor_id', $submitDonorId)->orderByDesc('appointment_id')->value('appointment_id');
    expect_true(! empty($appointmentId), 'Verified donor should be able to create appointment.');
    $created['appointment_ids'][] = (int) $appointmentId;

    echo json_encode([
        'pass' => true,
        'summary' => [
            'donor_upload_pending' => true,
            'private_document_exists' => true,
            'admin_document_view' => true,
            'admin_approve_updates_verification_and_donor' => true,
            'admin_reject_saves_reason' => true,
            'reupload_after_rejection' => true,
            'unverified_booking_blocked' => true,
            'verified_booking_allowed' => true,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if ($created['appointment_ids'] !== []) {
        DB::table('appointments')->whereIn('appointment_id', $created['appointment_ids'])->delete();
    }
    if ($created['verification_ids'] !== []) {
        DB::table('donor_verifications')->whereIn('verification_id', $created['verification_ids'])->delete();
    }
    foreach (array_filter($created['document_paths']) as $path) {
        Storage::disk('local')->delete($path);
    }
    if ($created['donor_ids'] !== []) {
        DB::table('notifications')->whereIn('donor_id', $created['donor_ids'])->delete();
        DB::table('eligibility_status')->whereIn('donor_id', $created['donor_ids'])->delete();
        DB::table('donor_authentication')->whereIn('donor_id', $created['donor_ids'])->delete();
        DB::table('donors')->whereIn('donor_id', $created['donor_ids'])->delete();
        DB::table('audit_logs')
            ->where('module_type', 'donor_verification')
            ->whereIn('target_id', $created['verification_ids'] ?: [0])
            ->delete();
    }
}
