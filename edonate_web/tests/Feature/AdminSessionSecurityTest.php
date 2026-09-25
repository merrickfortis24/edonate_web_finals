<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminSessionSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('admins', function (Blueprint $table): void {
            $table->increments('admin_id');
            $table->string('username')->nullable();
            $table->string('full_name')->nullable();
            $table->string('role')->default('admin');
            $table->boolean('is_active')->default(true);
        });

        DB::table('admins')->insert([
            'admin_id' => 1,
            'username' => 'secure-admin',
            'full_name' => 'Secure Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_only_route_uses_the_current_database_role_not_a_stale_session_role(): void
    {
        DB::table('admins')->where('admin_id', 1)->update(['role' => 'staff']);

        $this->withSession([
            'admin_id' => 1,
            'admin_role' => 'admin',
            'admin_last_activity_at' => now()->timestamp,
        ])->get('/admin/dashboard')
            ->assertRedirect(route('admin.unauthorized'));
    }

    public function test_inactive_admin_session_is_revoked(): void
    {
        DB::table('admins')->where('admin_id', 1)->update(['is_active' => false]);

        $this->withSession([
            'admin_id' => 1,
            'admin_role' => 'admin',
            'admin_last_activity_at' => now()->timestamp,
        ])->get('/admin/dashboard')
            ->assertRedirect(route('admin.login'))
            ->assertSessionMissing('admin_id');
    }

    public function test_inactivity_timeout_returns_a_json_401_for_ajax_requests(): void
    {
        Schema::create('admin_security_settings', function (Blueprint $table): void {
            $table->increments('admin_security_setting_id');
            $table->unsignedInteger('session_timeout_minutes')->default(5);
        });
        DB::table('admin_security_settings')->insert(['session_timeout_minutes' => 5]);

        $this->withSession([
            'admin_id' => 1,
            'admin_role' => 'admin',
            'admin_last_activity_at' => now()->subMinutes(6)->timestamp,
        ])->getJson('/admin/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'admin_session_expired')
            ->assertSessionMissing('admin_id');
    }
}
