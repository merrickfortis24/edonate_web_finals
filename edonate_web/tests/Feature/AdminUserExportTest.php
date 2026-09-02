<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['eligibility_status', 'locations', 'donor_authentication', 'blood_types', 'donors', 'admins'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('full_name')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('blood_types', function (Blueprint $table): void {
            $table->increments('blood_type_id');
            $table->string('blood_type');
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->increments('location_id');
            $table->string('street_address')->nullable();
            $table->string('barangay_name')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('contact_number')->nullable();
            $table->unsignedInteger('blood_type_id')->nullable();
            $table->string('blood_type_status')->nullable();
            $table->timestamp('blood_type_verified_at')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->date('date_registered')->nullable();
            $table->string('verification_status')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('donor_authentication', function (Blueprint $table): void {
            $table->increments('auth_id');
            $table->unsignedInteger('donor_id');
            $table->string('email');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('eligibility_status', function (Blueprint $table): void {
            $table->increments('eligibility_id');
            $table->unsignedInteger('donor_id');
            $table->string('status')->nullable();
            $table->date('next_eligible_date')->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach (['eligibility_status', 'locations', 'donor_authentication', 'blood_types', 'donors', 'admins'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_filtered_user_export_returns_csv_without_private_authentication_fields(): void
    {
        $adminId = DB::table('admins')->insertGetId([
            'full_name' => 'Export Admin',
            'username' => 'export-admin',
            'email' => 'export-admin@example.test',
            'password' => Hash::make('password-123'),
            'role' => 'admin',
            'created_at' => now(),
        ]);

        $bloodTypeId = DB::table('blood_types')->insertGetId(['blood_type' => 'O+']);
        $locationId = DB::table('locations')->insertGetId([
            'barangay_name' => 'San Sebastian',
            'city' => 'Lipa City',
            'province' => 'Batangas',
        ]);
        $donorId = DB::table('donors')->insertGetId([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'contact_number' => '09171234567',
            'blood_type_id' => $bloodTypeId,
            'blood_type_status' => 'self_reported',
            'location_id' => $locationId,
            'date_registered' => now()->toDateString(),
            'verification_status' => 'pending',
            'is_active' => true,
        ]);
        DB::table('donor_authentication')->insert([
            'donor_id' => $donorId,
            'email' => 'demo.donor001@example.test',
            'created_at' => now(),
        ]);
        DB::table('eligibility_status')->insert([
            'donor_id' => $donorId,
            'status' => 'eligible',
        ]);

        $this->withoutMiddleware([
            EnsureAdminAuthenticated::class,
            EnsureAdminRole::class,
        ])->withSession([
            'admin_id' => $adminId,
            'admin_role' => 'admin',
            'admin_username' => 'export-admin',
            'admin_full_name' => 'Export Admin',
        ]);

        $response = $this->get('/admin/users/export?search=Juan&status=eligible');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('"Donor ID","Donor Code","Full Name",Email', $content);
        $this->assertStringContainsString('Juan Dela Cruz', $content);
        $this->assertStringContainsString('demo.donor001@example.test', $content);
        $this->assertStringNotContainsString('password', strtolower($content));
        $this->assertStringNotContainsString('otp', strtolower($content));
    }
}
