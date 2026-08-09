<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RbacManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('admins');
        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('full_name')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->json('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_last_verified_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_rbac_users_endpoint_returns_existing_admins_and_normalizes_role_counts(): void
    {
        DB::table('admins')->insert([
            [
                'full_name' => 'Existing Admin',
                'username' => 'existing-admin',
                'email' => 'existing-admin@example.test',
                'role' => 'Admin',
                'created_at' => now()->subDay(),
            ],
            [
                'full_name' => 'Existing Staff',
                'username' => 'existing-staff',
                'email' => 'existing-staff@example.test',
                'role' => 'Staff',
                'created_at' => now(),
            ],
        ]);

        $this->withoutMiddleware([
            EnsureAdminAuthenticated::class,
            EnsureAdminRole::class,
        ]);

        $response = $this->getJson('/admin/rbac/users?per_page=10');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('summary.total_users', 2)
            ->assertJsonPath('summary.role_user_counts.1', 1)
            ->assertJsonPath('summary.role_user_counts.2', 1)
            ->assertJsonPath('data.0.roleIds.0', 2);
    }

    public function test_rbac_page_defers_bootstrap_modal_setup_until_it_is_available(): void
    {
        $this->withoutMiddleware([
            EnsureAdminAuthenticated::class,
            EnsureAdminRole::class,
        ]);

        $response = $this->get('/admin/rbac');

        $response->assertOk()
            ->assertSee('id="rbacAddUserBtn"', false)
            ->assertSee('eligibility_questions.manage', false)
            ->assertSee('blood_requests.match', false)
            ->assertSee('settings.manage', false)
            ->assertSee('function lazyModalController', false)
            ->assertSee('window.bootstrap.Modal.getOrCreateInstance(element)', false)
            ->assertDontSee('bootstrap.Modal.getOrCreateInstance(roleModalElement)', false);
    }
}
