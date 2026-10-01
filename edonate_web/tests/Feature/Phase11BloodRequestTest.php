<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use App\Services\BloodRequestService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase11BloodRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        (require database_path('migrations/2026_09_08_000000_create_privacy_receipts_table.php'))->up();
    }

    public function test_authorized_admin_can_create_blood_request_and_inventory_is_not_deducted(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $facilityId = $this->createFacility();
        DB::table('facility_blood_inventory')->insert([
            'facility_id' => $facilityId,
            'blood_type_id' => 4,
            'available_units' => 2,
            'low_stock_threshold' => 5,
            'last_updated' => now(),
        ]);

        $expiresAt = now()->addDay()->toIso8601String();
        $response = $this->withSession($this->adminSession())->postJson('/admin/blood-requests', [
            'facility_id' => $facilityId,
            'submission_key' => (string) \Illuminate\Support\Str::uuid(),
            'request_type' => 'replacement_donor',
            'needed_blood_type_id' => 4,
            'required_donors' => 5,
            'specific_match_required' => 1,
            'allow_other_blood_types' => true,
            'urgency' => 'emergency',
            'expires_at' => $expiresAt,
            'notes' => 'Facility requested replacement donors.',
        ]);

        $response->assertCreated()->assertJsonPath('request.required_donors', 5);
        $request = DB::table('blood_requests')->first();
        $this->assertNotNull($request);
        $this->assertSame('RDR-' . now()->format('Y') . '-000001', $request->request_reference);
        $this->assertSame(Carbon::parse($expiresAt)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'), $request->expires_at);
        $this->assertSame('open', $request->status);
        $this->assertSame(2, (int) DB::table('facility_blood_inventory')->where('facility_id', $facilityId)->where('blood_type_id', 4)->value('available_units'));
        $this->assertDatabaseHas('audit_logs', ['action_type' => 'blood_request_created']);

        $this->withSession($this->adminSession())->getJson('/admin/blood-requests/data')
            ->assertOk()->assertJsonPath('summary.open', 1)->assertJsonPath('data.0.required_donors', 5);
    }

    public function test_repeated_submission_key_returns_the_existing_request_without_creating_a_duplicate(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $payload = [
            'facility_id' => $this->createFacility(),
            'submission_key' => (string) \Illuminate\Support\Str::uuid(),
            'request_type' => 'blood_request',
            'needed_blood_type_id' => 4,
            'required_donors' => 1,
            'specific_match_required' => 1,
            'allow_other_blood_types' => false,
            'urgency' => 'urgent',
            'expires_at' => now()->addDay()->toIso8601String(),
            'notes' => 'Need one O positive donor.',
        ];

        $first = $this->withSession($this->adminSession())->postJson('/admin/blood-requests', $payload);
        $first->assertCreated();
        $requestId = $first->json('request.request_id');

        $this->withSession($this->adminSession())->postJson('/admin/blood-requests', $payload)
            ->assertOk()
            ->assertJsonPath('request.request_id', $requestId)
            ->assertJsonPath('request.status', 'open');

        $this->assertSame(1, DB::table('blood_requests')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'blood_request_created')->count());
    }

    public function test_blood_request_listing_returns_rows_and_summary_for_the_management_page(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $this->createBloodRequest([
            'request_reference' => 'DEMO-LIST-0001',
            'patient_reference_code' => 'DEMO-LIST-PATIENT-0001',
            'status' => 'open',
            'urgency' => 'emergency',
        ]);
        $this->createBloodRequest([
            'request_reference' => 'DEMO-LIST-0002',
            'patient_reference_code' => 'DEMO-LIST-PATIENT-0002',
            'status' => 'fulfilled',
            'urgency' => 'normal',
        ]);

        $response = $this->withSession($this->adminSession())
            ->getJson('/admin/blood-requests/data?per_page=10');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('summary.open', 1)
            ->assertJsonPath('summary.emergency', 1)
            ->assertJsonPath('summary.fulfilled', 1);
        $this->assertSame(
            ['DEMO-LIST-0002', 'DEMO-LIST-0001'],
            collect($response->json('data'))->pluck('request_reference')->all()
        );
    }

    public function test_app_origin_pending_review_request_is_searchable_visible_and_nullable_relations_are_preserved(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $this->withSession($this->adminSession())
            ->get('/admin/blood-requests')
            ->assertOk()
            ->assertSee('Admin Requests')
            ->assertSee('Donor Requests')
            ->assertDontSee('id="requestSource"')
            ->assertDontSee('<th>Source</th>', false);

        $donorId = (int) DB::table('donors')->insertGetId([
            'first_name' => 'Mika',
            'last_name' => 'Santos',
            'contact_number' => '09170000001',
        ], 'donor_id');
        DB::table('donor_authentication')->insert([
            'donor_id' => $donorId,
            'email' => 'mika.santos@example.test',
            'is_verified' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $appRequestId = $this->createBloodRequest([
            'facility_id' => null,
            'request_reference' => 'APP-RDR-2026-000001',
            'patient_reference_code' => 'APP-PATIENT-001',
            'request_source' => 'app',
            'requested_by_donor_id' => $donorId,
            'created_by_admin_id' => null,
            'status' => 'pending_review',
            'urgency' => 'urgent',
        ]);
        $adminRequestId = $this->createBloodRequest([
            'request_reference' => 'ADMIN-RDR-2026-000002',
            'request_source' => 'admin',
            'requested_by_donor_id' => null,
            'created_by_admin_id' => 1,
            'status' => 'open',
        ]);

        $this->withSession($this->adminSession())
            ->getJson('/admin/blood-requests/data?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('summary.sources.all', 2)
            ->assertJsonPath('summary.sources.admin', 1)
            ->assertJsonPath('summary.sources.app', 1);

        $filtered = $this->withSession($this->adminSession())
            ->getJson('/admin/blood-requests/data?status=pending_review&source=app&search=mika.santos%40example.test')
            ->assertOk()
            ->assertJsonPath('summary.pending_review', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.request_id', $appRequestId)
            ->assertJsonPath('data.0.request_reference', 'APP-RDR-2026-000001')
            ->assertJsonPath('data.0.request_source', 'app')
            ->assertJsonPath('data.0.status', 'pending_review')
            ->assertJsonPath('data.0.requester_name', 'Mika Santos')
            ->assertJsonPath('data.0.requester_email', 'mika.santos@example.test')
            ->assertJsonPath('data.0.requester_contact', '09170000001')
            ->assertJsonPath('data.0.facility_id', null)
            ->assertJsonPath('data.0.facility_name', 'Not specified');

        $this->withSession($this->adminSession())
            ->get("/admin/blood-requests/{$appRequestId}")
            ->assertOk()
            ->assertSee('Review donor-submitted request')
            ->assertSee('APP-RDR-2026-000001')
            ->assertSee('Mika Santos');

        $this->assertSame(1, (int) DB::table('blood_requests')->where('request_source', 'admin')->count());
        $this->assertSame(1, (int) DB::table('blood_requests')->where('request_id', $adminRequestId)->count());

        $this->withSession($this->adminSession())
            ->getJson('/admin/blood-requests/data?source=admin')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.request_id', $adminRequestId)
            ->assertJsonPath('data.0.request_source', 'admin');

        $this->withSession($this->adminSession())
            ->getJson('/admin/blood-requests/data?source=app')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.request_id', $appRequestId);
    }

    public function test_admin_can_approve_or_reject_only_pending_requests_and_rejection_requires_reason(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $donorId = (int) DB::table('donors')->insertGetId([
            'first_name' => 'Jules',
            'last_name' => 'Reyes',
        ], 'donor_id');
        $approveId = $this->createBloodRequest([
            'facility_id' => null,
            'request_reference' => 'APP-APPROVE-001',
            'requested_by_donor_id' => $donorId,
            'created_by_admin_id' => null,
            'request_source' => 'app',
            'status' => 'pending_review',
        ]);
        $rejectId = $this->createBloodRequest([
            'facility_id' => null,
            'request_reference' => 'APP-REJECT-001',
            'requested_by_donor_id' => $donorId,
            'created_by_admin_id' => null,
            'request_source' => 'app',
            'status' => 'pending_review',
        ]);

        $this->withSession($this->adminSession())
            ->patchJson("/admin/blood-requests/{$approveId}/approve")
            ->assertOk();
        $this->assertDatabaseHas('blood_requests', [
            'request_id' => $approveId,
            'status' => 'open',
            'reviewed_by_admin_id' => 1,
        ]);
        $this->assertNotNull(DB::table('blood_requests')->where('request_id', $approveId)->value('reviewed_at'));

        $this->withSession($this->adminSession())
            ->patchJson("/admin/blood-requests/{$rejectId}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->withSession($this->adminSession())
            ->patchJson("/admin/blood-requests/{$rejectId}/reject", ['reason' => 'The facility details could not be verified.'])
            ->assertOk();
        $this->assertDatabaseHas('blood_requests', [
            'request_id' => $rejectId,
            'status' => 'rejected',
            'reviewed_by_admin_id' => 1,
            'review_reason' => 'The facility details could not be verified.',
        ]);
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'blood_request_approved')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'blood_request_rejected')->count());

        $this->withSession($this->adminSession())
            ->patchJson("/admin/blood-requests/{$approveId}/approve")
            ->assertUnprocessable();
    }

    public function test_expiry_command_expires_only_due_open_requests_once_without_changing_inventory(): void
    {
        $facilityId = $this->createFacility();
        DB::table('facility_blood_inventory')->insert([
            'facility_id' => $facilityId,
            'blood_type_id' => 4,
            'available_units' => 7,
            'low_stock_threshold' => 2,
            'last_updated' => now(),
        ]);

        $expiredOpenId = $this->createBloodRequest([
            'facility_id' => $facilityId,
            'request_reference' => 'RDR-EXP-OPEN',
            'patient_reference_code' => 'RDR-EXP-OPEN',
            'status' => 'open',
            'expires_at' => now()->subMinute(),
        ]);
        $expiredInProgressId = $this->createBloodRequest([
            'facility_id' => $facilityId,
            'request_reference' => 'RDR-EXP-PROGRESS',
            'patient_reference_code' => 'RDR-EXP-PROGRESS',
            'status' => 'in_progress',
            'expires_at' => now()->subMinute(),
        ]);
        $futureId = $this->createBloodRequest([
            'facility_id' => $facilityId,
            'request_reference' => 'RDR-EXP-FUTURE',
            'patient_reference_code' => 'RDR-EXP-FUTURE',
            'status' => 'open',
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('blood-requests:expire')->assertSuccessful();

        $this->assertSame('expired', DB::table('blood_requests')->where('request_id', $expiredOpenId)->value('status'));
        $this->assertSame('expired', DB::table('blood_requests')->where('request_id', $expiredInProgressId)->value('status'));
        $this->assertSame('open', DB::table('blood_requests')->where('request_id', $futureId)->value('status'));
        $this->assertSame(2, DB::table('audit_logs')->where('action_type', 'blood_request_expired')->count());
        $this->assertSame(2, DB::table('admin_notifications')->where('notification_type', 'blood_request_expired')->count());
        $this->assertSame(7, DB::table('facility_blood_inventory')->where('facility_id', $facilityId)->value('available_units'));

        $this->artisan('blood-requests:expire')->assertSuccessful();

        $this->assertSame(2, DB::table('audit_logs')->where('action_type', 'blood_request_expired')->count());
        $this->assertSame(2, DB::table('admin_notifications')->where('notification_type', 'blood_request_expired')->count());
        $this->assertSame(7, DB::table('facility_blood_inventory')->where('facility_id', $facilityId)->value('available_units'));
    }

    public function test_validation_rejects_missing_facility_bad_blood_type_and_too_many_specific_matches(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $this->withSession($this->adminSession())->postJson('/admin/blood-requests', [
            'facility_id' => 999,
            'submission_key' => (string) \Illuminate\Support\Str::uuid(),
            'request_type' => 'replacement_donor',
            'needed_blood_type_id' => 999,
            'required_donors' => 0,
            'specific_match_required' => 2,
            'urgency' => 'rush',
        ])->assertUnprocessable()->assertJsonValidationErrors(['facility_id', 'needed_blood_type_id', 'required_donors', 'urgency']);

        $this->withSession($this->adminSession())->postJson('/admin/blood-requests', [
            'facility_id' => $this->createFacility(),
            'submission_key' => (string) \Illuminate\Support\Str::uuid(),
            'request_type' => 'replacement_donor',
            'needed_blood_type_id' => 4,
            'required_donors' => 1,
            'specific_match_required' => 2,
            'allow_other_blood_types' => true,
            'urgency' => 'normal',
        ])->assertUnprocessable()->assertJsonValidationErrors('specific_match_required');

        $this->withSession($this->adminSession())->postJson('/admin/blood-requests', [
            'facility_id' => $this->createFacility(),
            'submission_key' => (string) \Illuminate\Support\Str::uuid(),
            'request_type' => 'not_a_request_type',
            'needed_blood_type_id' => 4,
            'required_donors' => 1,
            'specific_match_required' => 1,
            'allow_other_blood_types' => 'sometimes',
            'urgency' => 'normal',
            'expires_at' => now()->subMinute()->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['request_type', 'allow_other_blood_types', 'expires_at']);

        $this->withSession($this->adminSession())->postJson('/admin/blood-requests', [
            'facility_id' => $this->createFacility(),
            'submission_key' => (string) \Illuminate\Support\Str::uuid(),
            'request_type' => 'blood_request',
            'needed_blood_type_id' => 4,
            'specific_match_required' => 1,
            'urgency' => 'normal',
        ])->assertUnprocessable()->assertJsonValidationErrors('required_donors');

        $this->withSession($this->adminSession())->postJson('/admin/blood-requests', [
            'facility_id' => $this->createFacility(),
            'request_type' => 'blood_request',
            'needed_blood_type_id' => 4,
            'required_donors' => 1,
            'specific_match_required' => 1,
            'allow_other_blood_types' => false,
            'urgency' => 'normal',
        ])->assertUnprocessable()->assertJsonValidationErrors('submission_key');
    }

    public function test_unexpected_save_error_returns_safe_message_without_server_details(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $this->mock(BloodRequestService::class, function ($mock): void {
            $mock->shouldReceive('create')->once()->andThrow(new \RuntimeException('private SQL diagnostic'));
        });

        $this->withSession($this->adminSession())->postJson('/admin/blood-requests', [
            'facility_id' => $this->createFacility(),
            'submission_key' => (string) \Illuminate\Support\Str::uuid(),
            'request_type' => 'blood_request',
            'needed_blood_type_id' => 4,
            'required_donors' => 1,
            'specific_match_required' => 1,
            'allow_other_blood_types' => false,
            'urgency' => 'normal',
        ])->assertServerError()
            ->assertJsonPath('message', 'We could not save this blood request due to a temporary server issue. Please try again; if the problem continues, contact an administrator.')
            ->assertDontSee('private SQL diagnostic');
    }

    public function test_matching_uses_verified_blood_type_latest_eligibility_waiting_period_and_active_appointment_rules(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $requestId = $this->createBloodRequest();
        $sameBarangay = $this->createLocation(['barangay_name' => 'Poblacion']);
        $sameCity = $this->createLocation(['barangay_name' => 'Balintawak']);

        $exact = $this->createQualifiedDonor($sameBarangay, 4);
        $other = $this->createQualifiedDonor($sameCity, 1);
        $this->createQualifiedDonor($sameBarangay, 4, ['donor_id' => 10, 'blood_type_status' => 'self_reported']);
        $this->createQualifiedDonor($sameBarangay, 4, ['donor_id' => 11, 'verification_status' => 'pending']);
        $this->createQualifiedDonor($sameBarangay, 4, ['donor_id' => 12], ['status' => 'temporary_deferred']);
        $this->createQualifiedDonor($sameBarangay, 4, ['donor_id' => 13], ['next_eligible_date' => Carbon::tomorrow()->toDateString()]);
        $scheduled = $this->createQualifiedDonor($sameBarangay, 4, ['donor_id' => 14]);
        DB::table('appointments')->insert([
            'donor_id' => $scheduled,
            'appointment_date' => Carbon::tomorrow()->toDateString(),
            'status' => 'confirmed',
        ]);

        $response = $this->withSession($this->adminSession())->getJson("/admin/blood-requests/{$requestId}/candidates");
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('donor_id')->all();

        $this->assertContains($exact, $ids);
        $this->assertContains($other, $ids);
        $this->assertNotContains(11, $ids);
        $this->assertNotContains(12, $ids);
        $this->assertNotContains(13, $ids);
        $this->assertNotContains(14, $ids);
        $this->assertSame($exact, $response->json('data.0.donor_id'));

        $exactResponse = $this->withSession($this->adminSession())->getJson("/admin/blood-requests/{$requestId}/candidates?filter=exact");
        $exactIds = collect($exactResponse->json('data'))->pluck('donor_id')->all();
        $this->assertContains($exact, $exactIds);
        $this->assertNotContains(10, $exactIds);
    }

    public function test_admin_can_notify_candidates_once_and_donor_can_respond_without_creating_appointment(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $requestId = $this->createBloodRequest();
        $donorId = $this->createQualifiedDonor($this->createLocation(), 4);

        $payload = ['donor_ids' => [$donorId]];
        $this->withSession($this->adminSession())->postJson("/admin/blood-requests/{$requestId}/notify", $payload)
            ->assertOk()
            ->assertJsonPath('notified_count', 1);
        $this->withSession($this->adminSession())->postJson("/admin/blood-requests/{$requestId}/notify", $payload)
            ->assertOk()
            ->assertJsonPath('notified_count', 0);

        $this->assertSame(1, DB::table('blood_request_donors')->where('request_id', $requestId)->where('donor_id', $donorId)->count());
        $this->assertSame(1, DB::table('notifications')->where('donor_id', $donorId)->where('notification_type', 'blood_request_invitation')->count());

        $this->withSession($this->donorSession($donorId))->post("/blood-requests/{$requestId}/interested", $this->privacyAcknowledgment())
            ->assertRedirect(route('donor.blood-requests.show', $requestId));

        $this->assertDatabaseHas('blood_request_donors', [
            'request_id' => $requestId,
            'donor_id' => $donorId,
            'status' => 'interested',
        ]);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_admin_created_request_shows_mobile_accepted_donors_and_repeated_interest_does_not_duplicate_them(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $created = $this->withSession($this->adminSession())->postJson('/admin/blood-requests', [
            'facility_id' => $this->createFacility(),
            'submission_key' => (string) \Illuminate\Support\Str::uuid(),
            'request_type' => 'blood_request',
            'needed_blood_type_id' => 7,
            'required_donors' => 2,
            'specific_match_required' => 2,
            'allow_other_blood_types' => false,
            'urgency' => 'urgent',
        ])->assertCreated();
        $requestId = $created->json('request.request_id');
        $this->assertDatabaseHas('blood_requests', [
            'request_id' => $requestId,
            'request_source' => 'admin',
            'status' => 'open',
        ]);
        $this->withSession($this->adminSession())->getJson('/admin/blood-requests/data')
            ->assertOk()->assertJsonPath('data.0.interested_count', 0);

        $first = $this->createQualifiedDonor($this->createLocation(), 7, ['contact_number' => '09170000001']);
        $second = $this->createQualifiedDonor($this->createLocation(['barangay_name' => 'Balintawak']), 7);

        // The mobile writer uses "accepted" in the existing shared response table.
        DB::table('blood_request_donors')->insert([
            'request_id' => $requestId,
            'donor_id' => $first,
            'match_type' => 'exact',
            'status' => 'accepted',
            'responded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession($this->adminSession())->getJson('/admin/blood-requests/data')
            ->assertOk()->assertJsonPath('data.0.interested_count', 1);
        $this->withSession($this->adminSession())->getJson("/admin/blood-requests/{$requestId}/details")
            ->assertOk()->assertJsonPath('summary.interested', 1);
        $this->withSession($this->adminSession())->getJson("/admin/blood-requests/{$requestId}/candidates?filter=interested")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.donor_id', $first)
            ->assertJsonPath('data.0.status', 'accepted')
            ->assertJsonPath('data.0.contact', '09170000001')
            ->assertJsonPath('data.0.barangay', 'Poblacion');
        $this->withSession($this->adminSession())->get("/admin/blood-requests/{$requestId}")
            ->assertOk()
            ->assertSee('Interested / Willing Donors')
            ->assertSee('id="interestedDonorCount">1', false);

        DB::table('blood_request_donors')->insert([
            'request_id' => $requestId,
            'donor_id' => $second,
            'match_type' => 'exact',
            'status' => 'accepted',
            'responded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->withSession($this->adminSession())->getJson('/admin/blood-requests/data')
            ->assertOk()->assertJsonPath('data.0.interested_count', 2);
        $responders = $this->withSession($this->adminSession())
            ->getJson("/admin/blood-requests/{$requestId}/candidates?filter=interested")
            ->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertEqualsCanonicalizing([$first, $second], collect($responders)->pluck('donor_id')->all());

        $this->withSession($this->donorSession($first))
            ->post("/blood-requests/{$requestId}/interested", $this->privacyAcknowledgment())
            ->assertRedirect(route('donor.blood-requests.show', $requestId));
        $respondedAt = DB::table('blood_request_donors')->where('request_id', $requestId)->where('donor_id', $first)->value('responded_at');
        $this->withSession($this->donorSession($first))
            ->post("/blood-requests/{$requestId}/interested", $this->privacyAcknowledgment())
            ->assertRedirect(route('donor.blood-requests.show', $requestId));
        $this->assertSame(2, DB::table('blood_request_donors')->where('request_id', $requestId)->count());
        $this->assertSame($respondedAt, DB::table('blood_request_donors')->where('request_id', $requestId)->where('donor_id', $first)->value('responded_at'));
        $this->withSession($this->adminSession())->getJson("/admin/blood-requests/{$requestId}/details")
            ->assertOk()->assertJsonPath('summary.interested', 2);
    }

    public function test_unnotified_donor_cannot_access_or_respond_to_request_and_cancelled_request_blocks_responses(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $requestId = $this->createBloodRequest();
        $notified = $this->createQualifiedDonor($this->createLocation(), 4);
        $other = $this->createQualifiedDonor($this->createLocation(['barangay_name' => 'Balintawak']), 4);
        DB::table('blood_request_donors')->insert([
            'request_id' => $requestId,
            'donor_id' => $notified,
            'match_type' => 'exact',
            'status' => 'notified',
            'notified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession($this->donorSession($other))->get("/blood-requests/{$requestId}")->assertNotFound();
        $this->withSession($this->donorSession($other))->post("/blood-requests/{$requestId}/interested", $this->privacyAcknowledgment())->assertSessionHasErrors('request');

        $this->withSession($this->adminSession())->patchJson("/admin/blood-requests/{$requestId}/cancel", ['reason' => 'Facility cancelled'])
            ->assertOk();
        $this->withSession($this->donorSession($notified))->post("/blood-requests/{$requestId}/interested", $this->privacyAcknowledgment())->assertSessionHasErrors('request');

        $this->assertDatabaseHas('blood_request_donors', [
            'request_id' => $requestId,
            'donor_id' => $notified,
            'status' => 'notified',
        ]);
    }

    public function test_admin_can_confirm_complete_candidate_and_exact_completion_fulfills_request(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $requestId = $this->createBloodRequest([
            'required_donors' => 1,
            'total_donors_needed' => 1,
            'specific_match_required' => 1,
            'specific_blood_type_required_count' => 1,
        ]);
        $donorId = $this->createQualifiedDonor($this->createLocation(), 4);
        DB::table('blood_request_donors')->insert([
            'request_id' => $requestId,
            'donor_id' => $donorId,
            'match_type' => 'exact',
            'status' => 'interested',
            'notified_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession($this->adminSession())
            ->patchJson("/admin/blood-requests/{$requestId}/donors/{$donorId}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $this->assertDatabaseHas('blood_request_donors', [
            'request_id' => $requestId,
            'donor_id' => $donorId,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('blood_requests', ['request_id' => $requestId, 'status' => 'open']);

        $this->withSession($this->adminSession())
            ->patchJson("/admin/blood-requests/{$requestId}/donors/{$donorId}/status", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        $this->assertDatabaseHas('blood_requests', ['request_id' => $requestId, 'status' => 'fulfilled']);
        $this->assertDatabaseHas('audit_logs', ['action_type' => 'blood_request_donor_status_updated']);
    }

    public function test_unauthenticated_user_cannot_access_admin_blood_requests(): void
    {
        $this->get('/admin/blood-requests')->assertRedirect(route('admin.login'));
    }

    /** @return array<string, mixed> */
    private function adminSession(): array
    {
        return [
            'admin_id' => 1,
            'admin_role' => 'admin',
            'admin_username' => 'admin',
            'admin_full_name' => 'Test Admin',
        ];
    }

    /** @return array<string, mixed> */
    private function donorSession(int $donorId): array
    {
        return [
            'donor_id' => $donorId,
            'donor_name' => 'Test Donor',
            'donor_email' => "donor{$donorId}@example.test",
        ];
    }

    private function createBloodRequest(array $overrides = []): int
    {
        $facilityId = array_key_exists('facility_id', $overrides) ? $overrides['facility_id'] : $this->createFacility();

        return (int) DB::table('blood_requests')->insertGetId(array_merge([
            'facility_id' => $facilityId,
            'request_reference' => 'RDR-2026-000001',
            'patient_reference_code' => 'RDR-2026-000001',
            'request_type' => 'replacement_donor',
            'needed_blood_type_id' => 4,
            'required_donors' => 5,
            'total_donors_needed' => 5,
            'specific_match_required' => 1,
            'specific_blood_type_required_count' => 1,
            'allow_other_blood_types' => true,
            'allow_any_blood_type_replacement' => true,
            'urgency' => 'emergency',
            'status' => 'open',
            'created_by_admin_id' => 1,
            'request_source' => 'admin',
            'requested_by_donor_id' => null,
            'reviewed_by_admin_id' => null,
            'reviewed_at' => null,
            'review_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides), 'request_id');
    }

    private function createFacility(array $overrides = []): int
    {
        return (int) DB::table('facilities')->insertGetId(array_merge([
            'facility_name' => 'Mediatrix Medical Center',
            'facility_type' => 'hospital',
            'address' => 'Lipa City',
            'barangay_name' => 'Poblacion',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'latitude' => 13.9419,
            'longitude' => 121.1644,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides), 'facility_id');
    }

    private function createLocation(array $overrides = []): int
    {
        return (int) DB::table('locations')->insertGetId(array_merge([
            'barangay_name' => 'Poblacion',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'latitude' => 13.9419,
            'longitude' => 121.1644,
        ], $overrides), 'location_id');
    }

    private function createQualifiedDonor(int $locationId, int $bloodTypeId, array $donor = [], array $eligibility = []): int
    {
        $donorId = (int) ($donor['donor_id'] ?? 0);
        $payload = array_merge([
            'first_name' => 'Private',
            'last_name' => 'Donor',
            'blood_type_id' => $bloodTypeId,
            'blood_type_status' => 'verified',
            'location_id' => $locationId,
            'verification_status' => 'verified',
            'date_registered' => now(),
        ], $donor);

        if ($donorId > 0) {
            DB::table('donors')->insert($payload);
        } else {
            $donorId = (int) DB::table('donors')->insertGetId($payload, 'donor_id');
        }

        DB::table('eligibility_status')->insert(array_merge([
            'donor_id' => $donorId,
            'status' => 'eligible',
            'next_eligible_date' => null,
        ], $eligibility, ['donor_id' => $donorId]));

        return $donorId;
    }

    private function buildSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['audit_logs', 'admin_notifications', 'notifications', 'blood_request_donors', 'blood_requests', 'facility_blood_inventory', 'facilities', 'donation_records', 'appointments', 'eligibility_status', 'donor_authentication', 'donors', 'locations', 'blood_types', 'admins'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('username')->nullable();
            $table->string('full_name')->nullable();
            $table->string('role')->default('Admin');
        });
        DB::table('admins')->insert(['admin_id' => 1, 'username' => 'admin', 'full_name' => 'Test Admin', 'role' => 'Admin']);

        Schema::create('blood_types', function (Blueprint $table): void {
            $table->increments('blood_type_id');
            $table->string('blood_type', 5);
        });
        foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $index => $bloodType) {
            DB::table('blood_types')->insert(['blood_type_id' => $index + 1, 'blood_type' => $bloodType]);
        }

        Schema::create('locations', function (Blueprint $table): void {
            $table->increments('location_id');
            $table->string('barangay_name')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
        });

        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->string('first_name')->nullable();
            $table->string('middle_initial')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix')->nullable();
            $table->string('contact_number')->nullable();
            $table->integer('blood_type_id')->nullable();
            $table->string('blood_type_status')->nullable();
            $table->integer('location_id')->nullable();
            $table->string('verification_status')->nullable();
            $table->timestamp('date_registered')->nullable();
        });

        Schema::create('donor_authentication', function (Blueprint $table): void {
            $table->increments('auth_id');
            $table->integer('donor_id');
            $table->string('email')->unique();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });

        Schema::create('eligibility_status', function (Blueprint $table): void {
            $table->increments('eligibility_id');
            $table->integer('donor_id')->nullable();
            $table->date('next_eligible_date')->nullable();
            $table->string('status')->nullable();
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->increments('appointment_id');
            $table->integer('donor_id')->nullable();
            $table->date('appointment_date')->nullable();
            $table->string('status')->nullable();
        });

        Schema::create('donation_records', function (Blueprint $table): void {
            $table->increments('donation_id');
            $table->integer('donor_id')->nullable();
            $table->integer('appointment_id')->nullable();
            $table->date('donation_date')->nullable();
        });

        Schema::create('facilities', function (Blueprint $table): void {
            $table->increments('facility_id');
            $table->string('facility_name', 150);
            $table->string('facility_type', 30);
            $table->text('address')->nullable();
            $table->string('barangay_name', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('facility_blood_inventory', function (Blueprint $table): void {
            $table->increments('inventory_id');
            $table->integer('facility_id');
            $table->integer('blood_type_id');
            $table->integer('available_units')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->dateTime('last_updated')->nullable();
        });

        Schema::create('blood_requests', function (Blueprint $table): void {
            $table->increments('request_id');
            $table->integer('facility_id')->nullable();
            $table->string('request_reference', 32)->nullable()->unique();
            $table->string('submission_key', 36)->nullable()->unique();
            $table->string('patient_reference_code', 100)->nullable();
            $table->string('request_type', 40)->default('replacement_donor');
            $table->integer('needed_blood_type_id')->nullable();
            $table->integer('required_donors')->default(1);
            $table->integer('total_donors_needed')->default(1);
            $table->integer('specific_match_required')->default(0);
            $table->integer('specific_blood_type_required_count')->default(0);
            $table->boolean('allow_other_blood_types')->default(false);
            $table->boolean('allow_any_blood_type_replacement')->default(false);
            $table->string('urgency', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->text('notes')->nullable();
            $table->integer('created_by_admin_id')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('fulfilled_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('fulfillment_note')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('blood_request_donors', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('request_id');
            $table->integer('donor_id');
            $table->string('match_type', 30)->default('replacement_any');
            $table->string('status', 30)->default('candidate');
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'donor_id']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->increments('notification_id');
            $table->integer('donor_id')->nullable();
            $table->text('message')->nullable();
            $table->string('notification_type', 50)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->boolean('push_sent')->default(false);
        });

        Schema::create('admin_notifications', function (Blueprint $table): void {
            $table->increments('admin_notification_id');
            $table->string('title')->nullable();
            $table->text('message');
            $table->string('notification_type')->nullable();
            $table->string('channel')->nullable();
            $table->string('related_type')->nullable();
            $table->integer('related_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->increments('audit_log_id');
            $table->integer('actor_admin_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action_type');
            $table->string('module_type')->nullable();
            $table->string('target_table')->nullable();
            $table->integer('target_id')->nullable();
            $table->string('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('result')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        (require database_path('migrations/2026_09_29_100000_add_mobile_blood_request_review_fields.php'))->up();
    }
}
