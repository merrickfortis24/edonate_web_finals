<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use App\Models\Donor;
use App\Services\AppointmentBookingService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase6EventAppointmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildSchema();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        (require database_path('migrations/2026_09_08_000000_create_privacy_receipts_table.php'))->up();
    }

    public function test_admin_can_create_valid_donation_event(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $response = $this->withSession($this->adminSession())->postJson('/admin/donation-events', [
            'title' => 'City Hall Blood Drive',
            'event_date' => Carbon::today()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'location_name' => 'Lipa City Hall',
            'address' => 'Main Hall',
            'max_capacity' => 50,
            'status' => 'open',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('donation_events', [
            'title' => 'City Hall Blood Drive',
            'status' => 'open',
            'created_by_admin_id' => 1,
        ]);
    }

    public function test_admin_cannot_create_event_with_invalid_time_range(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $response = $this->withSession($this->adminSession())->postJson('/admin/donation-events', [
            'title' => 'Invalid Event',
            'event_date' => Carbon::today()->addDay()->toDateString(),
            'start_time' => '12:00',
            'end_time' => '09:00',
            'location_name' => 'Lipa City Hall',
            'max_capacity' => 10,
            'status' => 'open',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('end_time');
    }

    public function test_admin_cannot_reduce_capacity_below_confirmed_bookings(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $eventId = $this->createEvent(['max_capacity' => 3]);
        $donorId = $this->createDonor(['verification_status' => 'verified']);
        $this->createAppointment($donorId, $eventId, ['status' => 'confirmed']);
        $this->createAppointment($this->createDonor(['verification_status' => 'verified']), $eventId, ['status' => 'completed']);

        $response = $this->withSession($this->adminSession())->putJson("/admin/donation-events/{$eventId}", [
            'title' => 'City Hall Blood Drive',
            'event_date' => Carbon::today()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'location_name' => 'Lipa City Hall',
            'address' => 'Main Hall',
            'max_capacity' => 1,
            'status' => 'open',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('max_capacity');
    }

    public function test_verified_and_eligible_donor_books_auto_confirmed_event_appointment(): void
    {
        $donorId = $this->createDonor(['verification_status' => 'verified']);
        $this->createDonorAuthentication($donorId);
        $this->createEligibility($donorId, ['status' => 'eligible']);
        $eventId = $this->createEvent();

        $response = $this->withSession($this->donorSession($donorId))->post('/appointments/book', [...$this->privacyAcknowledgment(),
            'event_id' => $eventId,
            'appointment_time' => '09:30',
            'appointment_date' => '2099-01-01',
            'donation_center' => 'Client Supplied Center',
        ]);

        $response->assertRedirect(route('donor.book-appointment'));
        $appointment = DB::table('appointments')->where('donor_id', $donorId)->where('event_id', $eventId)->first();
        $this->assertNotNull($appointment);
        $this->assertSame(Carbon::today()->addDay()->toDateString(), Carbon::parse($appointment->appointment_date)->toDateString());
        $this->assertSame('09:30:00', (string) $appointment->appointment_time);
        $this->assertSame('Lipa City Hall', (string) $appointment->donation_center);
        $this->assertSame('confirmed', (string) $appointment->status);
    }

    public function test_underage_donor_is_not_ready_to_book_an_appointment(): void
    {
        $donorId = $this->createDonor([
            'birthdate' => now()->subYears(18)->addDay()->toDateString(),
            'verification_status' => 'verified',
        ]);
        $this->createDonorAuthentication($donorId);
        $this->createEligibility($donorId, ['status' => 'eligible']);

        $readiness = app(AppointmentBookingService::class)
            ->bookingReadiness(Donor::query()->findOrFail($donorId));

        $this->assertFalse($readiness['allowed']);
        $this->assertStringContainsString('at least 18 years old', implode(' ', $readiness['messages']));
    }

    public function test_unverified_or_ineligible_donor_cannot_book(): void
    {
        $eventId = $this->createEvent();
        $unverifiedDonorId = $this->createDonor(['verification_status' => 'pending']);
        $this->createDonorAuthentication($unverifiedDonorId);
        $this->createEligibility($unverifiedDonorId, ['status' => 'eligible']);

        $this->withSession($this->donorSession($unverifiedDonorId))
            ->post('/appointments/book', [...$this->privacyAcknowledgment(), 'event_id' => $eventId, 'appointment_time' => '09:00'])
            ->assertSessionHasErrors('event_id');

        $ineligibleDonorId = $this->createDonor(['verification_status' => 'verified']);
        $this->createDonorAuthentication($ineligibleDonorId);
        $this->createEligibility($ineligibleDonorId, ['status' => 'not_eligible']);

        $this->withSession($this->donorSession($ineligibleDonorId))
            ->post('/appointments/book', [...$this->privacyAcknowledgment(), 'event_id' => $eventId, 'appointment_time' => '09:00'])
            ->assertSessionHasErrors('event_id');
    }

    public function test_temporarily_deferred_donor_cannot_book_before_next_eligible_date(): void
    {
        $donorId = $this->createDonor(['verification_status' => 'verified']);
        $this->createDonorAuthentication($donorId);
        $this->createEligibility($donorId, [
            'status' => 'eligible',
            'next_eligible_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);
        $eventId = $this->createEvent(['event_date' => Carbon::today()->addDay()->toDateString()]);

        $this->withSession($this->donorSession($donorId))
            ->post('/appointments/book', [...$this->privacyAcknowledgment(), 'event_id' => $eventId, 'appointment_time' => '09:00'])
            ->assertSessionHasErrors('event_id');
    }

    public function test_donor_cannot_book_closed_cancelled_past_full_or_duplicate_event(): void
    {
        $donorId = $this->createBookableDonor();

        foreach (['closed', 'cancelled'] as $status) {
            $eventId = $this->createEvent(['status' => $status]);
            $this->withSession($this->donorSession($donorId))
                ->post('/appointments/book', [...$this->privacyAcknowledgment(), 'event_id' => $eventId, 'appointment_time' => '09:00'])
                ->assertSessionHasErrors('event_id');
        }

        $pastEventId = $this->createEvent(['event_date' => Carbon::yesterday()->toDateString()]);
        $this->withSession($this->donorSession($donorId))
            ->post('/appointments/book', [...$this->privacyAcknowledgment(), 'event_id' => $pastEventId, 'appointment_time' => '09:00'])
            ->assertSessionHasErrors('event_id');

        $fullEventId = $this->createEvent(['max_capacity' => 1]);
        $this->createAppointment($this->createBookableDonor(), $fullEventId, ['status' => 'confirmed']);
        $this->withSession($this->donorSession($donorId))
            ->post('/appointments/book', [...$this->privacyAcknowledgment(), 'event_id' => $fullEventId, 'appointment_time' => '09:00'])
            ->assertSessionHasErrors('event_id');

        $duplicateEventId = $this->createEvent();
        $this->withSession($this->donorSession($donorId))
            ->post('/appointments/book', [...$this->privacyAcknowledgment(), 'event_id' => $duplicateEventId, 'appointment_time' => '09:00'])
            ->assertRedirect();
        $this->withSession($this->donorSession($donorId))
            ->post('/appointments/book', [...$this->privacyAcknowledgment(), 'event_id' => $duplicateEventId, 'appointment_time' => '09:30'])
            ->assertSessionHasErrors('event_id');
    }

    public function test_cancelled_appointment_releases_capacity(): void
    {
        $eventId = $this->createEvent(['max_capacity' => 1]);
        $firstDonorId = $this->createBookableDonor();
        $secondDonorId = $this->createBookableDonor();

        $this->withSession($this->donorSession($firstDonorId))
            ->post('/appointments/book', [...$this->privacyAcknowledgment(), 'event_id' => $eventId, 'appointment_time' => '09:00'])
            ->assertRedirect();

        $appointmentId = (int) DB::table('appointments')->where('donor_id', $firstDonorId)->value('appointment_id');
        $this->withSession($this->donorSession($firstDonorId))
            ->patch("/appointments/{$appointmentId}/cancel", ['cancellation_reason' => 'Unavailable'])
            ->assertRedirect();

        $this->withSession($this->donorSession($secondDonorId))
            ->post('/appointments/book', [...$this->privacyAcknowledgment(), 'event_id' => $eventId, 'appointment_time' => '09:30'])
            ->assertRedirect();

        $this->assertSame(1, DB::table('appointments')->where('event_id', $eventId)->where('status', 'confirmed')->count());
    }

    public function test_cancelling_event_cancels_future_active_appointments_but_not_completed(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $eventId = $this->createEvent();
        $futureAppointmentId = $this->createAppointment($this->createBookableDonor(), $eventId, ['status' => 'confirmed']);
        $completedAppointmentId = $this->createAppointment($this->createBookableDonor(), $eventId, ['status' => 'completed']);

        $this->withSession($this->adminSession())
            ->patchJson("/admin/donation-events/{$eventId}/cancel", ['reason' => 'Weather'])
            ->assertOk();

        $this->assertDatabaseHas('donation_events', ['event_id' => $eventId, 'status' => 'cancelled']);
        $this->assertDatabaseHas('appointments', ['appointment_id' => $futureAppointmentId, 'status' => 'cancelled']);
        $this->assertDatabaseHas('appointments', ['appointment_id' => $completedAppointmentId, 'status' => 'completed']);
    }

    public function test_event_data_uses_compact_status_appropriate_action_menus(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $this->createEvent(['title' => 'Open event', 'status' => 'open']);
        $this->createEvent(['title' => 'Closed event', 'status' => 'closed']);
        $this->createEvent(['title' => 'Cancelled event', 'status' => 'cancelled']);
        $this->createEvent(['title' => 'Completed event', 'status' => 'completed']);

        $rows = collect($this->withSession($this->adminSession())
            ->getJson('/admin/donation-events/data?per_page=100')
            ->assertOk()
            ->json('data'))
            ->keyBy('status');

        foreach (['open', 'closed', 'cancelled', 'completed'] as $status) {
            $actions = (string) $rows[$status]['actions_html'];
            $this->assertStringContainsString('event-action-btn--donors', $actions);
            $this->assertStringContainsString('event-action-btn--edit', $actions);
            $this->assertStringContainsString('data-bs-toggle="tooltip"', $actions);
        }

        $openActions = (string) $rows['open']['actions_html'];
        $this->assertStringContainsString('data-action="close"', $openActions);
        $this->assertStringContainsString('data-action="complete"', $openActions);
        $this->assertStringContainsString('data-action="cancel"', $openActions);
        $this->assertStringNotContainsString('data-action="open"', $openActions);

        $closedActions = (string) $rows['closed']['actions_html'];
        $this->assertStringContainsString('data-action="open"', $closedActions);
        $this->assertStringContainsString('data-action="complete"', $closedActions);
        $this->assertStringContainsString('data-action="cancel"', $closedActions);
        $this->assertStringNotContainsString('data-action="close"', $closedActions);

        foreach (['cancelled', 'completed'] as $status) {
            $actions = (string) $rows[$status]['actions_html'];
            $this->assertStringNotContainsString('event-action-dropdown', $actions);
            $this->assertStringNotContainsString('data-action="complete"', $actions);
            $this->assertStringNotContainsString('data-action="cancel"', $actions);
        }

        $this->assertStringContainsString('dropdown-item--danger', $openActions);
    }

    public function test_unauthorized_user_cannot_access_admin_event_management(): void
    {
        $this->get('/admin/donation-events')->assertRedirect(route('admin.login'));
    }

    private function buildSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['audit_logs', 'admin_notifications', 'notifications', 'donation_records', 'appointments', 'donation_events', 'eligibility_status', 'donor_authentication', 'donors', 'admins'] as $table) {
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

        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->string('first_name')->nullable();
            $table->string('middle_initial')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix')->nullable();
            $table->string('gender')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('contact_number')->nullable();
            $table->integer('blood_type_id')->nullable();
            $table->string('blood_type_status')->nullable();
            $table->integer('blood_type_verified_by_admin_id')->nullable();
            $table->timestamp('blood_type_verified_at')->nullable();
            $table->integer('location_id')->nullable();
            $table->timestamp('date_registered')->nullable();
            $table->string('verification_status')->default('unverified');
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
            $table->string('status', 50)->default('Scheduled');
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
            $table->integer('blood_units')->nullable();
            $table->text('remarks')->nullable();
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
    }

    private function createBookableDonor(): int
    {
        $donorId = $this->createDonor(['verification_status' => 'verified']);
        $this->createDonorAuthentication($donorId);
        $this->createEligibility($donorId, ['status' => 'eligible']);

        return $donorId;
    }

    private function createDonor(array $overrides = []): int
    {
        return (int) DB::table('donors')->insertGetId(array_merge([
            'first_name' => 'Test',
            'last_name' => 'Donor',
            'birthdate' => now()->subYears(25)->toDateString(),
            'verification_status' => 'unverified',
            'date_registered' => now(),
        ], $overrides), 'donor_id');
    }

    private function createDonorAuthentication(int $donorId): void
    {
        DB::table('donor_authentication')->insert([
            'donor_id' => $donorId,
            'email' => "donor{$donorId}@example.test",
            'is_verified' => true,
            'created_at' => now(),
        ]);
    }

    private function createEligibility(int $donorId, array $overrides = []): void
    {
        DB::table('eligibility_status')->insert(array_merge([
            'donor_id' => $donorId,
            'status' => 'eligible',
            'next_eligible_date' => null,
        ], $overrides));
    }

    private function createEvent(array $overrides = []): int
    {
        return (int) DB::table('donation_events')->insertGetId(array_merge([
            'title' => 'City Hall Blood Drive',
            'event_date' => Carbon::today()->addDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'location_name' => 'Lipa City Hall',
            'address' => 'Main Hall',
            'max_capacity' => 10,
            'status' => 'open',
            'created_by_admin_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides), 'event_id');
    }

    private function createAppointment(int $donorId, int $eventId, array $overrides = []): int
    {
        $event = DB::table('donation_events')->where('event_id', $eventId)->first();

        return (int) DB::table('appointments')->insertGetId(array_merge([
            'donor_id' => $donorId,
            'event_id' => $eventId,
            'appointment_date' => $event->event_date,
            'appointment_time' => '09:00:00',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
            'donation_center' => $event->location_name,
        ], $overrides), 'appointment_id');
    }

    private function donorSession(int $donorId): array
    {
        return [
            'donor_id' => $donorId,
            'donor_name' => 'Test Donor',
            'donor_email' => "donor{$donorId}@example.test",
        ];
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
