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
            ->assertJsonPath('summary.active_donors', 1)
            ->assertJsonPath('distribution.basis', 'verified');
        $this->assertSame(8, count($response->json('inventory')));

        $export = $this->get('/admin/report-analytics/export?range=year');
        $export->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Completed donations', $export->streamedContent());
        $this->assertStringNotContainsString('Private Donor', $export->streamedContent());
        $this->assertStringNotContainsString('private@example.test', $export->streamedContent());
    }

    public function test_custom_report_dates_are_validated_server_side(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $this->getJson('/admin/report-analytics/data?range=custom&start_date=2026-08-10&end_date=2026-08-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
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
        foreach (['audit_logs', 'notifications', 'blood_requests', 'facility_blood_inventory', 'facilities', 'donation_events', 'appointments', 'donation_records', 'eligibility_status', 'donor_authentication', 'donors', 'locations', 'blood_types', 'admins'] as $table) {
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
            $table->integer('blood_type_id')->nullable();
            $table->integer('location_id')->nullable();
            $table->string('date_registered')->nullable();
            $table->string('blood_type_status')->nullable();
            $table->string('verification_status')->nullable();
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
            $table->date('next_eligible_date')->nullable();
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
        ], $overrides));
    }
}
