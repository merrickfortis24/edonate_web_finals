<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase12IntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
    }

    public function test_reports_use_database_aggregates_and_privacy_safe_exports(): void
    {
        $this->seedReportRows();
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $response = $this->getJson('/admin/report-analytics/data?range=year');

        $response->assertOk()
            ->assertJsonPath('summary.total_donations', 2)
            ->assertJsonPath('summary.completed_donations', 1)
            ->assertJsonPath('summary.deferred_donations', 1)
            ->assertJsonPath('summary.open_requests', 1)
            ->assertJsonPath('summary.emergency_requests', 1)
            ->assertJsonPath('summary.verified_donors', 1)
            ->assertJsonPath('availability.summary.verified_donors', true)
            ->assertJsonPath('distribution.basis', 'verified');
        $this->assertSame(8, count($response->json('inventory')));

        $export = $this->get('/admin/report-analytics/export?range=year');
        $export->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Completed donations', $export->streamedContent());
        $this->assertStringNotContainsString('Private Donor', $export->streamedContent());
        $this->assertStringNotContainsString('private@example.test', $export->streamedContent());
    }

    public function test_report_date_and_blood_type_filters_match_metrics_charts_inventory_and_export(): void
    {
        $this->seedReportRows();
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $today = Carbon::today()->toDateString();
        $startDate = Carbon::today()->subDays(3)->toDateString();
        $query = http_build_query([
            'range' => 'custom',
            'start_date' => $startDate,
            'end_date' => $today,
            'blood_type_id' => 1,
        ]);

        $response = $this->getJson('/admin/report-analytics/data?'.$query);
        $response->assertOk()
            ->assertJsonPath('filters.range', 'custom')
            ->assertJsonPath('filters.blood_type_id', 1)
            ->assertJsonPath('summary.total_donations', 1)
            ->assertJsonPath('summary.completed_donations', 1)
            ->assertJsonPath('summary.deferred_donations', 0)
            ->assertJsonPath('summary.eligible_donors', 1)
            ->assertJsonPath('summary.pending_verification', 0)
            ->assertJsonPath('summary.verified_donor_accounts', 1)
            ->assertJsonPath('summary.total_inventory_units', 0)
            ->assertJsonPath('summary.out_of_stock_blood_types', 1)
            ->assertJsonPath('distribution.available', true)
            ->assertJsonPath('distribution.verified_total', 1)
            ->assertJsonPath('distribution.unknown_total', 0)
            ->assertJsonPath('distribution.items.0.label', 'A+')
            ->assertJsonPath('inventory.0.blood_type', 'A+')
            ->assertJsonPath('inventory.0.status', 'out_of_stock');
        $this->assertSame(1, array_sum($response->json('trend.donations')));
        $this->assertCount(1, $response->json('inventory'));

        $export = $this->get('/admin/report-analytics/export?'.$query);
        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('"Blood type filter",A+', $csv);
        $this->assertStringContainsString('"Blood-type distribution status",Available', $csv);
        $this->assertStringContainsString('A+,0,0,2,"Out of stock"', $csv);
        $this->assertStringNotContainsString('A-,10,0,', $csv);
    }

    public function test_each_report_date_preset_and_custom_range_returns_its_selected_period(): void
    {
        $this->seedReportRows();
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $today = Carbon::today();
        $expected = [
            'today' => [$today->toDateString(), $today->toDateString()],
            'week' => [$today->copy()->startOfWeek(Carbon::MONDAY)->toDateString(), $today->copy()->endOfWeek(Carbon::SUNDAY)->toDateString()],
            'month' => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
            'year' => [$today->copy()->startOfYear()->toDateString(), $today->copy()->endOfYear()->toDateString()],
        ];

        foreach ($expected as $range => [$start, $end]) {
            $response = $this->getJson('/admin/report-analytics/data?range='.$range);
            $response->assertOk()
                ->assertJsonPath('filters.range', $range)
                ->assertJsonPath('filters.start_date', $start)
                ->assertJsonPath('filters.end_date', $end)
                ->assertJsonPath('summary.total_donations', 2);
        }

        $customStart = $today->copy()->subDays(3)->toDateString();
        $custom = $this->getJson('/admin/report-analytics/data?'.http_build_query([
            'range' => 'custom',
            'start_date' => $customStart,
            'end_date' => $today->toDateString(),
        ]));
        $custom->assertOk()
            ->assertJsonPath('filters.range', 'custom')
            ->assertJsonPath('filters.start_date', $customStart)
            ->assertJsonPath('filters.end_date', $today->toDateString())
            ->assertJsonPath('summary.total_donations', 2);
    }

    public function test_facility_filter_does_not_show_unfiltered_donor_distribution_and_empty_period_is_zeroed(): void
    {
        $this->seedReportRows();
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $facilityResponse = $this->getJson('/admin/report-analytics/data?range=year&facility_id=1&blood_type_id=2');
        $facilityResponse->assertOk()
            ->assertJsonPath('filters.facility_id', 1)
            ->assertJsonPath('filters.blood_type_id', 2)
            ->assertJsonPath('distribution.available', false)
            ->assertJsonPath('distribution.verified_total', null)
            ->assertJsonPath('distribution.items', [])
            ->assertJsonPath('trend.donors_available', false)
            ->assertJsonPath('trend.donors', [])
            ->assertJsonPath('inventory.0.blood_type', 'A-')
            ->assertJsonPath('summary.total_inventory_units', 10);

        $facilityExport = $this->get('/admin/report-analytics/export?range=year&facility_id=1&blood_type_id=2');
        $this->assertStringContainsString('"Facility filter","Demo Facility"', $facilityExport->streamedContent());
        $this->assertStringContainsString('"Blood-type distribution status",Unavailable', $facilityExport->streamedContent());
        $this->assertStringContainsString('Donor profiles are not associated with facilities', $facilityExport->streamedContent());
        $this->assertStringContainsString('"Verified Donors",N/A', $facilityExport->streamedContent());
        $this->assertStringContainsString('"Donor trend status",Unavailable', $facilityExport->streamedContent());

        $empty = $this->getJson('/admin/report-analytics/data?range=custom&start_date=2019-01-01&end_date=2019-01-31&blood_type_id=2');
        $empty->assertOk()
            ->assertJsonPath('summary.total_donations', 0)
            ->assertJsonPath('summary.donors_in_period', 0)
            ->assertJsonPath('distribution.verified_total', 0)
            ->assertJsonPath('distribution.items', [])
            ->assertJsonPath('inventory.0.blood_type', 'A-');
        $this->assertSame(0, array_sum($empty->json('trend.donations')));
    }

    public function test_report_page_preserves_applied_filters_in_controls_and_export_link(): void
    {
        $this->seedReportRows();
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin']);

        $response = $this->get('/admin/report-analytics?range=custom&start_date=2026-09-01&end_date=2026-09-28&facility_id=1&blood_type_id=2');
        $response->assertOk()
            ->assertSee('value="custom" selected', false)
            ->assertSee('value="2026-09-01"', false)
            ->assertSee('value="2026-09-28"', false)
            ->assertSee('value="1" selected', false)
            ->assertSee('value="2" selected', false)
            ->assertSee('start_date=2026-09-01', false)
            ->assertSee('blood_type_id=2', false);
    }

    public function test_custom_report_dates_are_validated_server_side(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $this->getJson('/admin/report-analytics/data?range=custom&start_date=2026-08-10&end_date=2026-08-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_admin_reactivation_preserves_history_and_is_idempotent(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $this->seedDonor(3, 'Inactive', 'Donor', ['is_active' => false]);
        DB::table('donation_records')->insert([
            'donation_id' => 30,
            'donor_id' => 3,
            'donation_date' => Carbon::today()->subMonth()->toDateString(),
            'donation_status' => 'completed',
            'blood_units' => 1,
        ]);

        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->patchJson('/admin/users/3/reactivate')
            ->assertOk()
            ->assertJsonPath('donor.is_active', true);
        $this->assertDatabaseHas('donors', ['donor_id' => 3, 'is_active' => true]);
        $this->assertDatabaseHas('donation_records', ['donation_id' => 30, 'donor_id' => 3]);

        $auditCount = DB::table('audit_logs')->where('action_type', 'reactivate')->count();
        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->patchJson('/admin/users/3/reactivate')
            ->assertOk()
            ->assertJsonPath('message', 'This donor account is already active.');
        $this->assertSame($auditCount, DB::table('audit_logs')->where('action_type', 'reactivate')->count());
    }

    public function test_user_management_separates_accounts_and_moves_them_after_deactivate_and_reactivate(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $this->seedDonor(1, 'Active', 'One');
        $this->seedDonor(2, 'Active', 'Two');
        $this->seedDonor(3, 'Deactivated', 'One', ['is_active' => false]);
        DB::table('donation_records')->insert([
            'donation_id' => 31,
            'donor_id' => 1,
            'donation_date' => Carbon::today()->subMonth()->toDateString(),
            'donation_status' => 'completed',
            'blood_units' => 1,
        ]);

        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->get('/admin/users?account_status=deactivated')
            ->assertOk()
            ->assertSee('Deactivated Accounts')
            ->assertSee('aria-selected="true"', false)
            ->assertSee('id="userManagementActiveCount">2</span>', false)
            ->assertSee('id="userManagementDeactivatedCount">1</span>', false)
            ->assertSee('userManagementDeactivatedTab');

        $this->getJson('/admin/users/data?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.donor_id', 2)
            ->assertJsonPath('account_counts.active', 2)
            ->assertJsonPath('account_counts.deactivated', 1);

        $this->getJson('/admin/users/data?account_status=active&per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.donor_id', 1);

        $this->getJson('/admin/users/data?account_status=deactivated&search=Deactivated')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.donor_id', 3);

        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->patchJson('/admin/users/1/deactivate')
            ->assertOk()
            ->assertJsonPath('donor.is_active', false);

        $this->getJson('/admin/users/data?account_status=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('account_counts.active', 1)
            ->assertJsonPath('account_counts.deactivated', 2);
        $this->getJson('/admin/users/data?account_status=deactivated')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.donor_id', 3);

        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->patchJson('/admin/users/1/reactivate')
            ->assertOk()
            ->assertJsonPath('donor.is_active', true);

        $this->getJson('/admin/users/data?account_status=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('account_counts.active', 2)
            ->assertJsonPath('account_counts.deactivated', 1);
        $this->getJson('/admin/users/data?account_status=deactivated')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.donor_id', 3);

        $this->assertDatabaseHas('donation_records', ['donation_id' => 31, 'donor_id' => 1]);
    }

    public function test_admin_dashboard_marks_missing_inventory_unavailable_and_builds_sqlite_month_data(): void
    {
        $this->seedReportRows();
        Schema::dropIfExists('facility_blood_inventory');

        $method = new \ReflectionMethod(\App\Http\Controllers\AdminAuthController::class, 'buildAdminDashboardPayload');
        $method->setAccessible(true);
        $payload = $method->invoke(app(\App\Http\Controllers\AdminAuthController::class));

        $this->assertNull($payload['stats']['operational']['low_stock']);
        $this->assertNull($payload['stats']['operational']['out_of_stock']);
        $this->assertTrue($payload['monthly_donations']['available']);
        $this->assertSame(1, array_sum($payload['monthly_donations']['values']));
    }

    public function test_dashboard_links_activity_and_each_pending_approval_to_exact_record_ids(): void
    {
        $this->seedDonor(1, 'Pending', 'Identity');
        $this->seedDonor(2, 'Pending', 'Eligibility');
        $this->seedDonor(3, 'Pending', 'Appointment');
        $this->seedDonor(4, 'Approved', 'Identity');

        DB::table('donor_verifications')->insert([
            ['verification_id' => 44, 'donor_id' => 4, 'document_type' => 'national_id', 'document_path' => 'front.jpg', 'status' => 'verified', 'reviewed_by_admin_id' => 1, 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['verification_id' => 45, 'donor_id' => 1, 'document_type' => 'national_id', 'document_path' => 'pending.jpg', 'status' => 'pending', 'reviewed_by_admin_id' => null, 'reviewed_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $eligibilityId = DB::table('eligibility_status')->insertGetId([
            'donor_id' => 2,
            'status' => 'for_review',
        ], 'eligibility_id');
        $appointmentId = DB::table('appointments')->insertGetId([
            'donor_id' => 3,
            'event_id' => null,
            'appointment_date' => Carbon::tomorrow()->toDateString(),
            'appointment_time' => '09:00:00',
            'status' => 'pending',
            'created_at' => now(),
        ], 'appointment_id');
        DB::table('audit_logs')->insert([
            'actor_admin_id' => 1,
            'actor_name' => 'Reviewing Administrator',
            'action_type' => 'donor_verification_approved',
            'target_table' => 'donor_verifications',
            'target_id' => 44,
            'description' => 'Approved donor identity verification.',
            'created_at' => now(),
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $dashboard = $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->get('/admin/dashboard')
            ->assertOk();

        $dashboard->assertSee(route('admin.donor-verifications.index', ['focus' => 44]), false)
            ->assertSee(route('admin.donor-verifications.index', ['focus' => 45]), false)
            ->assertSee(route('admin.eligibility.index', ['focus' => $eligibilityId]), false)
            ->assertSee(route('admin.appointments', ['focus' => $appointmentId]), false)
            ->assertSee(route('admin.eligibility.index'), false);

        $verificationPage = $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->get('/admin/donor-verifications?focus=44&page=99&status=pending&search=not-a-match')
            ->assertOk()
            ->assertSee('id="verification-row-44"', false)
            ->assertSee('class="dashboard-record-highlight"', false)
            ->assertDontSee('id="verification-row-45"', false);
        $verificationPage->assertSee('scrollIntoView', false);

        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->get('/admin/eligibility?focus='.$eligibilityId)
            ->assertOk()
            ->assertSee('"focusId":'.$eligibilityId, false);

        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->getJson('/admin/eligibility/data?focus_id='.$eligibilityId.'&page=99&status=eligible&search=not-a-match')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.eligibility_id', $eligibilityId);

        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->get('/admin/appointments?focus='.$appointmentId)
            ->assertOk()
            ->assertSee('scrollIntoView', false);

        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->getJson('/admin/appointments/data?appointment_id='.$appointmentId.'&page=99&status=cancelled&search=not-a-match')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.appointment_id', $appointmentId);

        $this->assertStringContainsString('scrollIntoView', file_get_contents(base_path('../public_html/js/admin/eligibility-review.js')));
    }

    public function test_dashboard_pending_empty_state_is_truthful_and_restriction_review_cards_are_responsive(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $dashboard = $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('No pending approvals at the moment.');

        $dashboard->assertSee(route('admin.appointment-restrictions.index'), false)
            ->assertSee(route('admin.appointment-restrictions.index', ['appeal_status' => 'pending']), false);

        $styles = file_get_contents(resource_path('css/admin.css'));
        $this->assertIsString($styles);
        $this->assertStringContainsString('.dashboard-restriction-card {', $styles);
        $this->assertStringContainsString('align-items: flex-start;', $styles);
        $this->assertStringContainsString('@media (max-width: 575.98px)', $styles);
        $this->assertStringContainsString('dashboard-record-highlight', $styles);
    }

    public function test_pending_appeal_review_link_filters_to_only_donors_with_a_pending_appeal(): void
    {
        Schema::table('donors', function (Blueprint $table): void {
            $table->boolean('appointment_restricted')->default(false);
            $table->integer('consecutive_cancellations')->default(0);
            $table->string('restriction_status')->nullable();
            $table->timestamp('restricted_at')->nullable();
        });
        Schema::create('appointment_cancellations', function (Blueprint $table): void {
            $table->increments('cancellation_id');
            $table->integer('donor_id');
            $table->integer('appointment_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
        Schema::create('appointment_restrictions', function (Blueprint $table): void {
            $table->increments('restriction_id');
            $table->integer('donor_id');
            $table->string('status');
            $table->text('restriction_reason')->nullable();
            $table->timestamp('restricted_at')->nullable();
        });
        Schema::create('appointment_restriction_appeals', function (Blueprint $table): void {
            $table->increments('appeal_id');
            $table->integer('donor_id');
            $table->integer('restriction_id');
            $table->text('justification')->nullable();
            $table->string('status');
            $table->timestamp('submitted_at')->nullable();
        });

        $this->seedDonor(1, 'Pending', 'Appeal', ['appointment_restricted' => true, 'restriction_status' => 'restricted', 'restricted_at' => now()]);
        $this->seedDonor(2, 'Other', 'Restricted', ['appointment_restricted' => true, 'restriction_status' => 'restricted', 'restricted_at' => now()]);
        DB::table('appointment_restrictions')->insert([
            ['restriction_id' => 1, 'donor_id' => 1, 'status' => 'active', 'restriction_reason' => 'Review', 'restricted_at' => now()],
            ['restriction_id' => 2, 'donor_id' => 2, 'status' => 'active', 'restriction_reason' => 'Review', 'restricted_at' => now()],
        ]);
        DB::table('appointment_restriction_appeals')->insert([
            ['appeal_id' => 1, 'donor_id' => 1, 'restriction_id' => 1, 'justification' => 'Please review', 'status' => 'pending', 'submitted_at' => now()],
            ['appeal_id' => 2, 'donor_id' => 2, 'restriction_id' => 2, 'justification' => 'Already reviewed', 'status' => 'rejected', 'submitted_at' => now()],
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $this->withSession(['admin_id' => 1, 'admin_role' => 'admin'])
            ->get('/admin/appointment-restrictions?appeal_status=pending')
            ->assertOk()
            ->assertSee('Pending Appeal')
            ->assertSee('Showing restricted donors with a pending appeal.')
            ->assertSee('Pending Appeal')
            ->assertDontSee('Other Restricted');
    }

    public function test_report_marks_missing_metrics_unavailable_and_never_guesses_facility_by_name(): void
    {
        $this->seedReportRows();
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        Schema::dropIfExists('facility_blood_inventory');

        $this->getJson('/admin/report-analytics/data?range=year&facility_id=1')
            ->assertOk()
            ->assertJsonPath('availability.summary.low_stock_blood_types', false)
            ->assertJsonPath('availability.summary.completed_donations', false)
            ->assertJsonPath('availability.summary.events_in_period', false);
    }

    public function test_donation_records_endpoint_returns_processing_rows_and_stats(): void
    {
        $this->seedDonor(1, 'Processing', 'Donor', [
            'blood_type_id' => 1,
            'blood_type_status' => 'verified',
            'verification_status' => 'verified',
        ]);
        DB::table('donor_authentication')->insert([
            'donor_id' => 1,
            'email' => 'processing@example.test',
            'is_verified' => 1,
            'created_at' => now(),
        ]);
        DB::table('eligibility_status')->insert([
            'donor_id' => 1,
            'status' => 'eligible',
            'next_eligible_date' => null,
        ]);
        DB::table('donation_events')->insert([
            'event_id' => 1,
            'title' => 'Processing Test Event',
            'event_date' => Carbon::today()->toDateString(),
            'location_name' => 'Processing Center',
            'status' => 'open',
        ]);
        DB::table('appointments')->insert([
            [
                'appointment_id' => 1,
                'donor_id' => 1,
                'event_id' => 1,
                'appointment_date' => Carbon::today()->toDateString(),
                'appointment_time' => '09:00:00',
                'status' => 'confirmed',
                'checked_in_at' => null,
                'completed_at' => null,
                'cancellation_reason' => null,
                'created_at' => now(),
                'donation_center' => 'Processing Center',
            ],
            [
                'appointment_id' => 2,
                'donor_id' => 1,
                'event_id' => 1,
                'appointment_date' => Carbon::today()->toDateString(),
                'appointment_time' => '10:00:00',
                'status' => 'completed',
                'checked_in_at' => null,
                'completed_at' => now(),
                'cancellation_reason' => null,
                'created_at' => now(),
                'donation_center' => 'Processing Center',
            ],
        ]);
        DB::table('donation_records')->insert([
            'donation_id' => 1,
            'donor_id' => 1,
            'appointment_id' => 2,
            'donation_date' => Carbon::today()->toDateString(),
            'donation_status' => 'completed',
            'blood_units' => 1,
            'verified_blood_type_id' => 1,
            'created_at' => now(),
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $response = $this->getJson('/admin/donation-records/data?per_page=10');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('stats.expected_today', 1)
            ->assertJsonPath('stats.completed', 1)
            ->assertJsonPath('data.0.event_title', 'Processing Test Event');
    }

    public function test_donor_notification_actions_are_scoped_to_the_current_donor(): void
    {
        $this->seedDonor(1, 'First', 'Donor');
        $this->seedDonor(2, 'Second', 'Donor');
        DB::table('notifications')->insert([
            ['notification_id' => 1, 'donor_id' => 1, 'message' => 'Own alert', 'notification_type' => 'appointment_booked', 'is_read' => 0, 'created_at' => now(), 'push_sent' => 0],
            ['notification_id' => 2, 'donor_id' => 2, 'message' => 'Other alert', 'notification_type' => 'appointment_booked', 'is_read' => 0, 'created_at' => now(), 'push_sent' => 0],
        ]);

        $this->withSession(['donor_id' => 1])
            ->patchJson('/notifications/2/read')
            ->assertNotFound();
        $this->assertDatabaseHas('notifications', ['notification_id' => 2, 'is_read' => 0]);

        $this->withSession(['donor_id' => 1])
            ->patchJson('/notifications/1/read')
            ->assertOk();
        $this->assertDatabaseHas('notifications', ['notification_id' => 1, 'is_read' => 1]);

        DB::table('notifications')->insert([
            'notification_id' => 3,
            'donor_id' => 1,
            'message' => 'Second own alert',
            'notification_type' => 'system',
            'is_read' => 0,
            'created_at' => now(),
            'push_sent' => 0,
        ]);
        $this->withSession(['donor_id' => 1])
            ->patchJson('/notifications/read-all')
            ->assertOk();
        $this->assertDatabaseHas('notifications', ['notification_id' => 3, 'is_read' => 1]);
        $this->assertDatabaseHas('notifications', ['notification_id' => 2, 'is_read' => 0]);
    }

    public function test_next_eligible_reminder_command_is_idempotent(): void
    {
        $this->seedDonor(1, 'Reminder', 'Donor');
        DB::table('eligibility_status')->insert([
            'donor_id' => 1,
            'status' => 'temporary_deferred',
            'next_eligible_date' => Carbon::yesterday()->toDateString(),
        ]);

        $this->artisan('edonate:eligibility-reminders')->assertExitCode(0);
        $this->artisan('edonate:eligibility-reminders')->assertExitCode(0);

        $this->assertSame(1, DB::table('notifications')->where('donor_id', 1)->where('notification_type', 'next_eligible_reminder')->count());
    }

    public function test_audit_details_redact_sensitive_metadata_and_support_filters(): void
    {
        DB::table('audit_logs')->insert([
            'audit_log_id' => 1,
            'actor_admin_id' => 1,
            'actor_name' => 'Test Admin',
            'actor_role' => 'Admin',
            'action_type' => 'security_policy_update',
            'module_type' => 'security',
            'target_table' => 'admin_security_settings',
            'target_id' => null,
            'description' => 'Updated test policy.',
            'ip_address' => '127.0.0.1',
            'result' => 'warning',
            'metadata' => json_encode(['email' => 'private@example.test', 'password' => 'secret', 'reason' => 'Reviewed']),
            'created_at' => now(),
        ]);
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $this->getJson('/admin/audit-logs/1')
            ->assertOk()
            ->assertJsonPath('data.metadata.email', '[redacted]')
            ->assertJsonPath('data.metadata.password', '[redacted]')
            ->assertJsonPath('data.metadata.reason', 'Reviewed');

        $list = $this->getJson('/admin/audit-logs/data?module_type=security&result=warning&start_date=2026-01-01&end_date=2026-12-31');
        $list->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertStringNotContainsString('private@example.test', $list->getContent());
    }

    private function buildSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['appointment_restriction_reviews', 'appointment_restriction_appeals', 'appointment_restrictions', 'appointment_cancellations', 'donor_verifications', 'audit_logs', 'notifications', 'blood_requests', 'facility_blood_inventory', 'facilities', 'donation_events', 'appointments', 'donation_records', 'eligibility_status', 'donor_authentication', 'donors', 'locations', 'blood_types', 'admins'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('blood_types', function (Blueprint $table): void {
            $table->increments('blood_type_id');
            $table->string('blood_type', 5);
        });
        foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $index => $type) {
            DB::table('blood_types')->insert(['blood_type_id' => $index + 1, 'blood_type' => $type]);
        }

        Schema::create('locations', function (Blueprint $table): void {
            $table->increments('location_id');
            $table->string('barangay_name')->nullable();
            $table->string('street_address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });
        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('full_name')->nullable();
            $table->string('username')->nullable();
        });
        DB::table('admins')->insert([
            'admin_id' => 1,
            'full_name' => 'Test Admin',
            'username' => 'test-admin',
        ]);
        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('contact_number')->nullable();
            $table->integer('blood_type_id')->nullable();
            $table->integer('location_id')->nullable();
            $table->string('date_registered')->nullable();
            $table->string('blood_type_status')->nullable();
            $table->string('verification_status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('blood_type_verified_by_admin_id')->nullable();
            $table->timestamp('blood_type_verified_at')->nullable();
        });
        Schema::create('donor_authentication', function (Blueprint $table): void {
            $table->increments('auth_id');
            $table->integer('donor_id')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('eligibility_status', function (Blueprint $table): void {
            $table->increments('eligibility_id');
            $table->integer('donor_id')->nullable();
            $table->string('status')->nullable();
            $table->date('last_donation_date')->nullable();
            $table->date('next_eligible_date')->nullable();
        });
        Schema::create('donor_verifications', function (Blueprint $table): void {
            $table->increments('verification_id');
            $table->integer('donor_id');
            $table->string('document_type')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_back_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->integer('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('donation_events', function (Blueprint $table): void {
            $table->increments('event_id');
            $table->string('title')->nullable();
            $table->date('event_date')->nullable();
            $table->string('location_name')->nullable();
            $table->string('status')->nullable();
        });
        Schema::create('appointments', function (Blueprint $table): void {
            $table->increments('appointment_id');
            $table->integer('donor_id')->nullable();
            $table->integer('event_id')->nullable();
            $table->date('appointment_date')->nullable();
            $table->time('appointment_time')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('donation_center')->nullable();
        });
        Schema::create('donation_records', function (Blueprint $table): void {
            $table->increments('donation_id');
            $table->integer('donor_id')->nullable();
            $table->integer('appointment_id')->nullable();
            $table->date('donation_date')->nullable();
            $table->string('donation_status')->nullable();
            $table->integer('blood_units')->nullable();
            $table->integer('verified_blood_type_id')->nullable();
            $table->string('remarks')->nullable();
            $table->string('deferred_reason')->nullable();
            $table->integer('recorded_by_admin_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('facilities', function (Blueprint $table): void {
            $table->increments('facility_id');
            $table->string('facility_name');
        });
        Schema::create('facility_blood_inventory', function (Blueprint $table): void {
            $table->increments('inventory_id');
            $table->integer('facility_id')->nullable();
            $table->integer('blood_type_id')->nullable();
            $table->integer('available_units')->default(0);
            $table->integer('reserved_units')->default(0);
            $table->integer('low_stock_threshold')->default(5);
        });
        Schema::create('blood_requests', function (Blueprint $table): void {
            $table->increments('request_id');
            $table->integer('facility_id')->nullable();
            $table->integer('needed_blood_type_id')->nullable();
            $table->integer('required_donors')->default(1);
            $table->integer('total_donors_needed')->default(1);
            $table->string('status')->nullable();
            $table->string('urgency')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->increments('notification_id');
            $table->integer('donor_id')->nullable();
            $table->text('message');
            $table->string('notification_type')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->boolean('push_sent')->default(false);
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
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('result')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    private function seedReportRows(): void
    {
        $this->seedDonor(1, 'Private', 'Donor', ['blood_type_id' => 1, 'blood_type_status' => 'verified', 'verification_status' => 'verified']);
        $this->seedDonor(2, 'Self', 'Reported', ['blood_type_id' => 2, 'blood_type_status' => 'self_reported', 'verification_status' => 'pending']);
        DB::table('eligibility_status')->insert([
            ['donor_id' => 1, 'status' => 'eligible', 'next_eligible_date' => null],
            ['donor_id' => 2, 'status' => 'temporary_deferred', 'next_eligible_date' => Carbon::tomorrow()->toDateString()],
        ]);
        DB::table('donation_events')->insert(['event_id' => 1, 'event_date' => Carbon::today()->toDateString(), 'location_name' => 'Demo Facility', 'status' => 'completed']);
        DB::table('appointments')->insert([
            ['appointment_id' => 1, 'donor_id' => 1, 'event_id' => 1, 'appointment_date' => Carbon::today()->toDateString(), 'status' => 'completed'],
            ['appointment_id' => 2, 'donor_id' => 2, 'event_id' => 1, 'appointment_date' => Carbon::today()->toDateString(), 'status' => 'deferred_on_site'],
        ]);
        DB::table('donation_records')->insert([
            ['donation_id' => 1, 'donor_id' => 1, 'appointment_id' => 1, 'donation_date' => Carbon::today()->toDateString(), 'donation_status' => 'completed', 'blood_units' => 1],
            ['donation_id' => 2, 'donor_id' => 2, 'appointment_id' => 2, 'donation_date' => Carbon::today()->toDateString(), 'donation_status' => 'deferred', 'blood_units' => 0],
        ]);
        DB::table('facilities')->insert(['facility_id' => 1, 'facility_name' => 'Demo Facility']);
        foreach (range(1, 8) as $typeId) {
            DB::table('facility_blood_inventory')->insert(['facility_id' => 1, 'blood_type_id' => $typeId, 'available_units' => $typeId === 1 ? 0 : 10, 'reserved_units' => 0, 'low_stock_threshold' => 5]);
        }
        DB::table('blood_requests')->insert([
            ['facility_id' => 1, 'needed_blood_type_id' => 1, 'required_donors' => 2, 'total_donors_needed' => 2, 'status' => 'open', 'urgency' => 'emergency', 'created_at' => now()],
            ['facility_id' => 1, 'needed_blood_type_id' => 2, 'required_donors' => 1, 'total_donors_needed' => 1, 'status' => 'fulfilled', 'urgency' => 'normal', 'created_at' => now()],
        ]);
    }

    private function seedDonor(int $id, string $firstName, string $lastName, array $overrides = []): void
    {
        DB::table('donors')->insert(array_merge([
            'donor_id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'blood_type_id' => 1,
            'location_id' => null,
            'date_registered' => Carbon::today()->subDays(2)->toDateTimeString(),
            'blood_type_status' => 'verified',
            'verification_status' => 'verified',
            'is_active' => true,
        ], $overrides));
    }
}
