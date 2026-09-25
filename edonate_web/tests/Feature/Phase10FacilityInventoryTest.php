<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase10FacilityInventoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
    }

    public function test_admin_can_create_a_valid_facility_and_invalid_type_is_rejected(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class]);

        $this->withSession($this->adminSession())->postJson('/admin/facilities', [
            'facility_name' => 'Lipa Community Blood Bank',
            'facility_type' => 'blood_bank',
            'address' => 'P. Torres Street',
            'barangay_name' => 'Poblacion',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'latitude' => 13.9419,
            'longitude' => 121.1644,
            'contact_number' => '09170000000',
            'status' => 'active',
        ])->assertCreated();

        $this->assertDatabaseHas('facilities', [
            'facility_name' => 'Lipa Community Blood Bank',
            'facility_type' => 'blood_bank',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action_type' => 'facility_created']);

        $this->withSession($this->adminSession())->postJson('/admin/facilities', [
            'facility_name' => 'Invalid Facility',
            'facility_type' => 'warehouse',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('facility_type');

        $this->withSession($this->adminSession())->postJson('/admin/facilities', [
            'facility_name' => 'Invalid Coordinates',
            'facility_type' => 'clinic',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'latitude' => 91,
            'longitude' => 181,
            'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_staff_can_view_but_cannot_change_facilities_or_inventory(): void
    {
        $facilityId = $this->createFacility();
        $this->withoutMiddleware([EnsureAdminAuthenticated::class]);
        $staff = $this->adminSession('staff', 2);

        $this->withSession($staff)->getJson('/admin/facilities/data')->assertOk();
        $this->withSession($staff)->get("/admin/facilities/{$facilityId}/inventory/data")->assertOk();
        $this->withSession($staff)->putJson("/admin/facilities/{$facilityId}/inventory", [
            'inventory' => [['blood_type_id' => 1, 'available_units' => 5]],
            'reason' => 'Stock count',
        ])->assertRedirect(route('admin.unauthorized'));
    }

    public function test_facility_listing_returns_rows_and_summary_for_the_management_page(): void
    {
        $facilityId = $this->createFacility(['facility_name' => 'Demo Listing Facility']);
        DB::table('facility_blood_inventory')->insert([
            'facility_id' => $facilityId,
            'blood_type_id' => 1,
            'available_units' => 8,
            'reserved_units' => 0,
            'low_stock_threshold' => 5,
            'last_updated' => now(),
        ]);
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $this->withSession($this->adminSession())->getJson('/admin/facilities/data?per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.active', 1)
            ->assertJsonPath('data.0.facility_id', $facilityId)
            ->assertJsonPath('data.0.facility_name', 'Demo Listing Facility')
            ->assertJsonPath('data.0.total_available_units', 8);
    }

    public function test_inventory_update_is_atomic_and_creates_history_and_audit_records(): void
    {
        $facilityId = $this->createFacility();
        $this->withoutMiddleware([EnsureAdminAuthenticated::class]);

        $response = $this->withSession($this->adminSession())->putJson("/admin/facilities/{$facilityId}/inventory", [
            'inventory' => [
                ['blood_type_id' => 1, 'available_units' => 8, 'low_stock_threshold' => 5],
                ['blood_type_id' => 2, 'available_units' => 3, 'low_stock_threshold' => 5],
                ['blood_type_id' => 3, 'available_units' => 0, 'low_stock_threshold' => 5],
            ],
            'reason' => 'Verified physical stock count',
        ]);

        $response->assertOk()->assertJsonPath('data.inventory.0.status', 'available');
        $this->assertDatabaseHas('facility_blood_inventory', [
            'facility_id' => $facilityId,
            'blood_type_id' => 2,
            'available_units' => 3,
        ]);
        $this->assertDatabaseCount('facility_blood_inventory_logs', 3);
        $this->assertDatabaseHas('audit_logs', ['action_type' => 'blood_inventory_updated']);

        $this->withSession($this->adminSession())->putJson("/admin/facilities/{$facilityId}/inventory", [
            'inventory' => [['blood_type_id' => 1, 'available_units' => 9, 'low_stock_threshold' => 5]],
            'reason' => 'Updated physical stock count',
        ])->assertOk();
        $this->assertDatabaseCount('facility_blood_inventory', 3);
        $this->assertDatabaseCount('facility_blood_inventory_logs', 4);

        $this->withSession($this->adminSession())->putJson("/admin/facilities/{$facilityId}/inventory", [
            'inventory' => [['blood_type_id' => 1, 'available_units' => -1]],
            'reason' => 'Invalid stock count',
        ])->assertUnprocessable()->assertJsonValidationErrors('inventory.0.available_units');
        $this->assertDatabaseCount('facility_blood_inventory_logs', 4);

        $this->withSession($this->adminSession())->putJson("/admin/facilities/{$facilityId}/inventory", [
            'inventory' => [['blood_type_id' => 999, 'available_units' => 1]],
            'reason' => 'Invalid blood type',
        ])->assertUnprocessable()->assertJsonValidationErrors('inventory.0.blood_type_id');

        $this->withSession($this->adminSession())->putJson('/admin/facilities/999/inventory', [
            'inventory' => [['blood_type_id' => 1, 'available_units' => 1]],
            'reason' => 'Invalid facility',
        ])->assertNotFound();
    }

    public function test_facility_map_returns_aggregate_inventory_with_zero_fallback_and_no_admin_data(): void
    {
        $mapped = $this->createFacility();
        $unmapped = $this->createFacility([
            'facility_name' => 'Unmapped Clinic',
            'facility_type' => 'clinic',
            'latitude' => null,
            'longitude' => null,
        ]);
        DB::table('facility_blood_inventory')->insert([
            'facility_id' => $mapped,
            'blood_type_id' => 1,
            'available_units' => 8,
            'reserved_units' => 0,
            'low_stock_threshold' => 5,
            'last_updated' => now(),
            'updated_by_admin_id' => 1,
        ]);
        DB::table('facility_blood_inventory')->insert([
            [
                'facility_id' => $mapped,
                'blood_type_id' => 2,
                'available_units' => 3,
                'reserved_units' => 0,
                'low_stock_threshold' => 5,
                'last_updated' => now(),
                'updated_by_admin_id' => 1,
            ],
            [
                'facility_id' => $mapped,
                'blood_type_id' => 3,
                'available_units' => 0,
                'reserved_units' => 0,
                'low_stock_threshold' => 5,
                'last_updated' => now(),
                'updated_by_admin_id' => 1,
            ],
        ]);
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $response = $this->getJson('/admin/blood-availability/facilities?blood_type=A%2B');
        $response->assertOk();
        $payload = $response->json();

        $this->assertSame(2, $payload['summary']['facilities']);
        $this->assertSame(1, $payload['summary']['mapped_facilities']);
        $this->assertSame(8, $payload['summary']['total_units']);
        $this->assertCount(2, $payload['facilities']);
        $this->assertCount(1, $payload['map_points']);
        $this->assertSame(3, $payload['facilities'][0]['blood_types']['A-']['units']);
        $this->assertSame('low', $payload['facilities'][0]['blood_types']['A-']['status']);
        $this->assertSame('out_of_stock', $payload['facilities'][0]['blood_types']['B+']['status']);
        $this->assertSame(0, $payload['facilities'][0]['blood_types']['O-']['units']);
        $this->assertStringNotContainsString('updated_by_admin_id', $response->getContent());
        $this->assertStringNotContainsString('admin_id', $response->getContent());
        $this->assertSame($unmapped, $payload['facilities'][1]['facility_id']);

        $this->getJson('/admin/blood-availability/facilities?facility_type=clinic')
            ->assertOk()
            ->assertJsonPath('summary.facilities', 1)
            ->assertJsonPath('facilities.0.facility_id', $unmapped);
    }

    public function test_inactive_facilities_are_not_exposed_on_the_inventory_map(): void
    {
        $this->createFacility();
        $this->createFacility(['facility_name' => 'Inactive Hospital', 'status' => 'inactive']);
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);

        $this->getJson('/admin/blood-availability/facilities')
            ->assertOk()
            ->assertJsonPath('summary.facilities', 1)
            ->assertJsonCount(1, 'facilities');
    }

    /** @return array<string, mixed> */
    private function adminSession(string $role = 'admin', int $id = 1): array
    {
        return [
            'admin_id' => $id,
            'admin_role' => $role,
            'admin_username' => $role,
            'admin_full_name' => ucfirst($role) . ' User',
        ];
    }

    private function createFacility(array $overrides = []): int
    {
        return (int) DB::table('facilities')->insertGetId(array_merge([
            'facility_name' => 'Lipa Medical Center',
            'facility_type' => 'hospital',
            'address' => 'Lipa City',
            'barangay_name' => 'Poblacion',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'latitude' => 13.9419,
            'longitude' => 121.1644,
            'contact_number' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides), 'facility_id');
    }

    private function buildSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['audit_logs', 'facility_blood_inventory_logs', 'facility_blood_inventory', 'facilities', 'blood_types', 'admins'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('username');
            $table->string('full_name')->nullable();
            $table->string('role');
        });
        DB::table('admins')->insert([
            ['admin_id' => 1, 'username' => 'admin', 'full_name' => 'Admin User', 'role' => 'Admin'],
            ['admin_id' => 2, 'username' => 'staff', 'full_name' => 'Staff User', 'role' => 'Staff'],
        ]);

        Schema::create('blood_types', function (Blueprint $table): void {
            $table->increments('blood_type_id');
            $table->string('blood_type', 5);
        });
        foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $index => $bloodType) {
            DB::table('blood_types')->insert(['blood_type_id' => $index + 1, 'blood_type' => $bloodType]);
        }

        Schema::create('facilities', function (Blueprint $table): void {
            $table->increments('facility_id');
            $table->string('facility_name', 150);
            $table->string('facility_type', 30);
            $table->text('address')->nullable();
            $table->string('barangay_name', 100)->nullable();
            $table->string('city', 100);
            $table->string('province', 100);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('facility_blood_inventory', function (Blueprint $table): void {
            $table->increments('inventory_id');
            $table->integer('facility_id');
            $table->integer('blood_type_id');
            $table->integer('available_units')->default(0);
            $table->integer('reserved_units')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->dateTime('last_updated')->nullable();
            $table->integer('updated_by_admin_id')->nullable();
            $table->unique(['facility_id', 'blood_type_id']);
        });

        Schema::create('facility_blood_inventory_logs', function (Blueprint $table): void {
            $table->increments('inventory_log_id');
            $table->integer('facility_id');
            $table->integer('blood_type_id');
            $table->integer('previous_units');
            $table->integer('new_units');
            $table->integer('change_amount');
            $table->string('action_type');
            $table->string('reason', 500);
            $table->integer('updated_by_admin_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->increments('audit_log_id');
            $table->integer('actor_admin_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action_type', 50);
            $table->string('module_type')->nullable();
            $table->string('target_table')->nullable();
            $table->integer('target_id')->nullable();
            $table->string('description', 255);
            $table->string('ip_address')->nullable();
            $table->string('result')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
