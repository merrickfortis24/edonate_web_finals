<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase7DonationProcessingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildSchema();
    }

    public function test_admin_can_check_in_confirmed_appointment(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $appointmentId = $this->createAppointment(['status' => 'confirmed']);

        $this->withSession($this->adminSession())
            ->patchJson("/admin/appointments/{$appointmentId}/check-in")
            ->assertOk();

        $this->assertDatabaseHas('appointments', [
            'appointment_id' => $appointmentId,
            'status' => 'checked_in',
            'admin_id' => 1,
        ]);
        $this->assertNotNull(DB::table('appointments')->where('appointment_id', $appointmentId)->value('checked_in_at'));
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => 'appointment_checked_in',
            'target_table' => 'appointments',
            'target_id' => $appointmentId,
        ]);
    }

    public function test_future_appointment_cannot_be_checked_in(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $appointmentId = $this->createAppointment([
            'appointment_date' => Carbon::tomorrow()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->withSession($this->adminSession())
            ->patchJson("/admin/appointments/{$appointmentId}/check-in")
            ->assertUnprocessable();
    }

    public function test_checked_in_appointment_can_be_completed_once(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $appointmentId = $this->createAppointment([
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
        $donorId = (int) DB::table('appointments')->where('appointment_id', $appointmentId)->value('donor_id');

        $this->withSession($this->adminSession())
            ->patchJson("/admin/appointments/{$appointmentId}/complete", [
                'blood_units' => 1,
                'donation_date' => Carbon::today()->toDateString(),
                'remarks' => 'Successful donation.',
            ])
            ->assertOk();

        $this->withSession($this->adminSession())
            ->patchJson("/admin/appointments/{$appointmentId}/complete", [
                'blood_units' => 1,
                'donation_date' => Carbon::today()->toDateString(),
            ])
            ->assertOk();

        $this->assertSame(1, DB::table('donation_records')->where('appointment_id', $appointmentId)->count());
        $this->assertDatabaseHas('donation_records', [
            'appointment_id' => $appointmentId,
            'donor_id' => $donorId,
            'donation_status' => 'completed',
            'blood_units' => 1,
            'recorded_by_admin_id' => 1,
        ]);
        $this->assertDatabaseHas('appointments', [
            'appointment_id' => $appointmentId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('eligibility_status', [
            'donor_id' => $donorId,
            'status' => 'temporary_deferred',
            'next_eligible_date' => Carbon::today()->addDays(56)->toDateString(),
        ]);
        $this->assertSame(1, DB::table('notifications')->where('donor_id', $donorId)->where('notification_type', 'donation_completed')->count());
    }

    public function test_confirmed_appointment_cannot_skip_check_in_to_complete(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $appointmentId = $this->createAppointment(['status' => 'confirmed']);

        $this->withSession($this->adminSession())
            ->patchJson("/admin/appointments/{$appointmentId}/complete", [
                'blood_units' => 1,
                'donation_date' => Carbon::today()->toDateString(),
            ])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('donation_records', ['appointment_id' => $appointmentId]);
    }

    public function test_checked_in_appointment_can_be_deferred_on_site(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $appointmentId = $this->createAppointment([
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
        $donorId = (int) DB::table('appointments')->where('appointment_id', $appointmentId)->value('donor_id');

        $this->withSession($this->adminSession())
            ->patchJson("/admin/appointments/{$appointmentId}/defer", [
                'deferred_reason' => 'Blood pressure was outside the safe donation range.',
                'next_eligible_date' => Carbon::today()->addWeeks(2)->toDateString(),
                'remarks' => 'Recheck at next visit.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('appointments', [
            'appointment_id' => $appointmentId,
            'status' => 'deferred_on_site',
        ]);
        $this->assertDatabaseHas('donation_records', [
            'appointment_id' => $appointmentId,
            'donation_status' => 'deferred',
            'blood_units' => 0,
        ]);
        $this->assertDatabaseHas('eligibility_status', [
            'donor_id' => $donorId,
            'status' => 'temporary_deferred',
            'next_eligible_date' => Carbon::today()->addWeeks(2)->toDateString(),
        ]);
    }

    public function test_no_show_requires_past_confirmed_appointment_and_creates_no_donation_record(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $futureAppointmentId = $this->createAppointment([
            'appointment_date' => Carbon::tomorrow()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->withSession($this->adminSession())
            ->patchJson("/admin/appointments/{$futureAppointmentId}/no-show")
            ->assertUnprocessable();

        $pastAppointmentId = $this->createAppointment([
            'appointment_date' => Carbon::yesterday()->toDateString(),
            'appointment_time' => '08:00:00',
            'status' => 'confirmed',
        ]);

        $this->withSession($this->adminSession())
            ->patchJson("/admin/appointments/{$pastAppointmentId}/no-show")
            ->assertOk();

        $this->assertDatabaseHas('appointments', [
            'appointment_id' => $pastAppointmentId,
            'status' => 'no_show',
        ]);
        $this->assertDatabaseMissing('donation_records', ['appointment_id' => $pastAppointmentId]);
    }

    public function test_staff_cannot_access_processing_without_admin_middleware_permission(): void
    {
        $this->get('/admin/donation-records')->assertRedirect(route('admin.login'));
    }

    private function buildSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['audit_logs', 'admin_notifications', 'notifications', 'donation_records', 'appointments', 'donation_events', 'eligibility_status', 'donor_authentication', 'blood_types', 'donors', 'admins'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('admins', function (Blueprint $table): void {
            $table->integer('admin_id')->primary();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('full_name')->nullable();
            $table->string('role')->default('Admin');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('blood_types', function (Blueprint $table): void {
            $table->increments('blood_type_id');
            $table->string('blood_type');
        });

        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->integer('blood_type_id')->nullable();
            $table->timestamp('date_registered')->nullable();
            $table->string('verification_status')->default('verified');
        });

        Schema::create('donor_authentication', function (Blueprint $table): void {
            $table->increments('auth_id');
            $table->integer('donor_id');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_verified')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('eligibility_status', function (Blueprint $table): void {
            $table->increments('eligibility_id');
            $table->integer('donor_id');
            $table->date('last_donation_date')->nullable();
            $table->date('next_eligible_date')->nullable();
            $table->string('status')->nullable();
            $table->text('result_reason')->nullable();
            $table->text('recommendation_message')->nullable();
            $table->string('source')->nullable();
            $table->integer('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
        });

        Schema::create('donation_events', function (Blueprint $table): void {
            $table->increments('event_id');
            $table->string('title', 150);
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location_name', 150);
            $table->text('address')->nullable();
            $table->integer('max_capacity')->default(100);
            $table->string('status')->default('open');
            $table->integer('created_by_admin_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->increments('appointment_id');
            $table->integer('donor_id')->nullable();
            $table->integer('event_id')->nullable();
            $table->date('appointment_date');
            $table->time('appointment_time')->nullable();
            $table->string('status', 50)->default('confirmed');
            $table->timestamp('completed_at')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('admin_id')->nullable();
            $table->string('donation_center', 100)->nullable();
        });

        Schema::create('donation_records', function (Blueprint $table): void {
            $table->increments('donation_id');
            $table->integer('donor_id')->nullable();
            $table->integer('appointment_id')->nullable();
            $table->date('donation_date')->nullable();
            $table->string('donation_status')->default('completed');
            $table->integer('blood_units')->nullable();
            $table->integer('verified_blood_type_id')->nullable();
            $table->text('remarks')->nullable();
            $table->text('deferred_reason')->nullable();
            $table->integer('recorded_by_admin_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->increments('notification_id');
            $table->integer('donor_id');
            $table->text('message');
            $table->string('notification_type')->nullable();
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
            $table->bigIncrements('audit_log_id');
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action_type');
            $table->string('module_type')->nullable();
            $table->string('target_table')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('description');
            $table->string('ip_address')->nullable();
            $table->string('result')->default('success');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        DB::table('admins')->insert([
            'admin_id' => 1,
            'username' => 'admin',
            'email' => 'admin@example.test',
            'full_name' => 'Test Admin',
            'role' => 'Admin',
            'created_at' => now(),
        ]);

        DB::table('blood_types')->insert([
            'blood_type_id' => 1,
            'blood_type' => 'O+',
        ]);
    }

    private function createAppointment(array $overrides = []): int
    {
        $donorId = (int) DB::table('donors')->insertGetId([
            'first_name' => 'Test',
            'last_name' => 'Donor',
            'blood_type_id' => 1,
            'verification_status' => 'verified',
            'date_registered' => now(),
        ], 'donor_id');

        DB::table('donor_authentication')->insert([
            'donor_id' => $donorId,
            'email' => "donor{$donorId}@example.test",
            'is_verified' => true,
            'created_at' => now(),
        ]);

        DB::table('eligibility_status')->insert([
            'donor_id' => $donorId,
            'status' => 'eligible',
            'next_eligible_date' => null,
        ]);

        $eventId = (int) DB::table('donation_events')->insertGetId([
            'title' => 'City Hall Blood Drive',
            'event_date' => Carbon::today()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'location_name' => 'Lipa City Hall',
            'max_capacity' => 10,
            'status' => 'open',
            'created_by_admin_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'event_id');

        return (int) DB::table('appointments')->insertGetId(array_merge([
            'donor_id' => $donorId,
            'event_id' => $eventId,
            'appointment_date' => Carbon::today()->toDateString(),
            'appointment_time' => '09:00:00',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
            'donation_center' => 'Lipa City Hall',
        ], $overrides), 'appointment_id');
    }

    private function adminSession(): array
    {
        return [
            'admin_id' => 1,
            'admin_role' => 'admin',
            'admin_username' => 'admin',
            'admin_full_name' => 'Test Admin',
        ];
    }
}
