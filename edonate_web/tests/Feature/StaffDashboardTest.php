<?php

namespace Tests\Feature;

use App\Services\StaffDashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();
        foreach (['audit_logs', 'facility_blood_inventory', 'facilities', 'blood_requests', 'donation_records', 'appointments'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }

    public function test_staff_dashboard_uses_live_counts_and_audit_activity(): void
    {
        $this->createOperationalSchema();
        DB::table('appointments')->insert([
            ['appointment_date' => today(), 'status' => 'confirmed'],
            ['appointment_date' => today(), 'status' => 'checked_in'],
            ['appointment_date' => today(), 'status' => 'completed'],
        ]);
        DB::table('donation_records')->insert([
            ['donation_date' => today(), 'donation_status' => 'completed'],
            ['donation_date' => today(), 'donation_status' => 'deferred'],
        ]);
        DB::table('blood_requests')->insert([
            ['status' => 'open', 'urgency' => 'emergency'],
            ['status' => 'in_progress', 'urgency' => 'normal'],
            ['status' => 'fulfilled', 'urgency' => 'normal'],
        ]);
        DB::table('facilities')->insert([['status' => 'active'], ['status' => 'inactive']]);
        DB::table('facility_blood_inventory')->insert([
            ['facility_id' => 1, 'available_units' => 2, 'low_stock_threshold' => 5],
            ['facility_id' => 1, 'available_units' => 8, 'low_stock_threshold' => 5],
        ]);
        DB::table('audit_logs')->insert([
            'action_type' => 'appointment_checked_in',
            'description' => 'Checked in appointment AP001.',
            'actor_name' => 'Staff User',
            'created_at' => now(),
        ]);

        $payload = app(StaffDashboardService::class)->build();

        $this->assertSame(3, $payload['metrics']['appointments_today']['value']);
        $this->assertSame(1, $payload['metrics']['confirmed_today']['value']);
        $this->assertSame(1, $payload['metrics']['checked_in_today']['value']);
        $this->assertSame(1, $payload['metrics']['completed_today']['value']);
        $this->assertSame(2, $payload['metrics']['open_requests']['value']);
        $this->assertSame(1, $payload['metrics']['emergency_requests']['value']);
        $this->assertSame(1, $payload['metrics']['active_facilities']['value']);
        $this->assertSame(1, $payload['metrics']['inventory_alerts']['value']);
        $this->assertSame('Appointment Checked In', $payload['activities'][0]['title']);
        $this->assertSame('Staff User', $payload['activities'][0]['actor']);
    }

    public function test_missing_dashboard_tables_are_unavailable_instead_of_fake_zeroes(): void
    {
        $payload = app(StaffDashboardService::class)->build();

        $this->assertFalse($payload['metrics']['appointments_today']['available']);
        $this->assertNull($payload['metrics']['appointments_today']['value']);
        $this->assertFalse($payload['metrics']['inventory_alerts']['available']);
        $this->assertFalse($payload['activity_available']);
        $this->assertSame([], $payload['activities']);
    }

    private function createOperationalSchema(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->increments('appointment_id');
            $table->date('appointment_date');
            $table->string('status');
        });
        Schema::create('donation_records', function (Blueprint $table): void {
            $table->increments('donation_id');
            $table->date('donation_date');
            $table->string('donation_status');
        });
        Schema::create('blood_requests', function (Blueprint $table): void {
            $table->increments('request_id');
            $table->string('status');
            $table->string('urgency');
        });
        Schema::create('facilities', function (Blueprint $table): void {
            $table->increments('facility_id');
            $table->string('status');
        });
        Schema::create('facility_blood_inventory', function (Blueprint $table): void {
            $table->increments('inventory_id');
            $table->integer('facility_id');
            $table->integer('available_units');
            $table->integer('low_stock_threshold');
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->increments('audit_log_id');
            $table->string('action_type');
            $table->string('description');
            $table->string('actor_name')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
