<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase9BloodAvailabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
    }

    public function test_only_currently_available_verified_eligible_donors_are_aggregated(): void
    {
        $balintawak = $this->createLocation([
            'barangay_code' => '042101001',
            'barangay_name' => 'Balintawak',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'latitude' => 13.952100,
            'longitude' => 121.123400,
        ]);
        $pangao = $this->createLocation([
            'barangay_name' => 'Pangao',
            'city' => 'Lipa City',
            'province' => 'Batangas',
        ]);

        $this->createQualifiedDonor($balintawak, 1);
        $this->createQualifiedDonor($balintawak, 2, [
            'donor_id' => 2,
            'first_name' => 'Scheduled',
        ]);
        DB::table('appointments')->insert([
            'donor_id' => 2,
            'appointment_date' => Carbon::tomorrow()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->createQualifiedDonor($pangao, 3, [
            'donor_id' => 3,
            'blood_type_id' => 3,
        ]);

        $this->createQualifiedDonor($balintawak, 1, [
            'donor_id' => 4,
            'verification_status' => 'pending',
        ]);
        $this->createQualifiedDonor($balintawak, 1, [
            'donor_id' => 5,
            'blood_type_status' => 'self_reported',
        ]);
        $this->createQualifiedDonor($balintawak, 1, [
            'donor_id' => 6,
        ], [
            'eligibility_id' => 6,
            'status' => 'eligible',
        ]);
        DB::table('eligibility_status')->insert([
            'eligibility_id' => 7,
            'donor_id' => 6,
            'status' => 'temporary_deferred',
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $response = $this->getJson('/admin/blood-availability/map-data');

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame(2, $payload['summary']['available_donors']);
        $this->assertSame(1, $payload['summary']['scheduled_donors']);
        $this->assertSame(1, $payload['blood_types']['A+']);
        $this->assertSame(1, $payload['blood_types']['O-']);
        $this->assertCount(2, $payload['barangays']);
        $this->assertCount(1, $payload['map_points']);
        $this->assertStringNotContainsString('Scheduled', $response->getContent());
        $this->assertStringNotContainsString('donor_id', $response->getContent());
        $this->assertStringNotContainsString('first_name', $response->getContent());
    }

    public function test_blood_type_filter_returns_aggregate_data_only(): void
    {
        $locationId = $this->createLocation([
            'barangay_code' => null,
            'barangay_name' => 'Balintawak',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'latitude' => 13.952100,
            'longitude' => 121.123400,
        ]);
        $this->createQualifiedDonor($locationId, 1);
        $this->createQualifiedDonor($locationId, 2, ['donor_id' => 2]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $response = $this->getJson('/admin/blood-availability/map-data?blood_type=O%2B');

        $response->assertOk();
        $payload = $response->json();
        $this->assertSame(1, $payload['summary']['available_donors']);
        $this->assertSame(1, $payload['blood_types']['O+']);
        $this->assertSame(0, $payload['blood_types']['A+']);
    }

    public function test_unauthenticated_user_cannot_access_map_endpoint(): void
    {
        $this->get('/admin/blood-availability/map-data')->assertRedirect(route('admin.login'));
    }

    private function buildSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['appointments', 'eligibility_status', 'donors', 'locations', 'blood_types'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('blood_types', function (Blueprint $table): void {
            $table->increments('blood_type_id');
            $table->string('blood_type', 5);
        });
        DB::table('blood_types')->insert([
            ['blood_type_id' => 1, 'blood_type' => 'A+'],
            ['blood_type_id' => 2, 'blood_type' => 'O+'],
            ['blood_type_id' => 3, 'blood_type' => 'O-'],
        ]);

        Schema::create('locations', function (Blueprint $table): void {
            $table->increments('location_id');
            $table->string('barangay_code')->nullable();
            $table->string('barangay_name')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
        });

        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->integer('blood_type_id')->nullable();
            $table->string('blood_type_status')->nullable();
            $table->integer('location_id')->nullable();
            $table->string('verification_status')->nullable();
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
    }

    private function createLocation(array $overrides = []): int
    {
        return (int) DB::table('locations')->insertGetId(array_merge([
            'barangay_name' => 'Balintawak',
            'city' => 'Lipa City',
            'province' => 'Batangas',
        ], $overrides), 'location_id');
    }

    private function createQualifiedDonor(int $locationId, int $bloodTypeId, array $donor = [], array $eligibility = []): int
    {
        $donorId = (int) DB::table('donors')->insertGetId(array_merge([
            'first_name' => 'Private',
            'last_name' => 'Donor',
            'blood_type_id' => $bloodTypeId,
            'blood_type_status' => 'verified',
            'location_id' => $locationId,
            'verification_status' => 'verified',
        ], $donor), 'donor_id');

        DB::table('eligibility_status')->insert(array_merge([
            'donor_id' => $donorId,
            'status' => 'eligible',
            'next_eligible_date' => null,
        ], $eligibility, ['donor_id' => $donorId]));

        return $donorId;
    }
}
