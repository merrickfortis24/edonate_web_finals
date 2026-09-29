<?php

namespace Tests\Feature;

use Database\Seeders\StagingDemoAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class StagingDemoAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('EDONATE_ALLOW_STAGING_DEMO_ADMIN');
        putenv('EDONATE_STAGING_DEMO_ADMIN_PASSWORD');

        parent::tearDown();
    }

    public function test_it_creates_only_the_dedicated_active_admin_with_a_hashed_password_and_two_factor_enrollment_required(): void
    {
        $this->allowTestProvisioning();

        (new StagingDemoAdminSeeder())->run();

        $admin = DB::table('admins')->where('email', 'staging.admin@example.test')->first();

        $this->assertNotNull($admin);
        $this->assertSame('staging_demo_admin', $admin->username);
        $this->assertSame('admin', strtolower($admin->role));
        $this->assertTrue((bool) $admin->is_active);
        $this->assertFalse((bool) $admin->two_factor_enabled);
        $this->assertTrue(Hash::check(str_repeat('a', 48), $admin->password));
        $this->assertNotSame(str_repeat('a', 48), $admin->password);
        $this->assertSame(1, DB::table('admins')->count());
    }

    public function test_it_refuses_to_reset_an_existing_staging_admin_password(): void
    {
        $this->allowTestProvisioning();
        $originalHash = Hash::make('existing-staging-admin-password');

        DB::table('admins')->insert([
            'username' => 'staging_demo_admin',
            'email' => 'staging.admin@example.test',
            'password' => $originalHash,
            'full_name' => 'eDonate Staging Demo Admin',
            'role' => 'admin',
            'is_active' => true,
            'auth_version' => 0,
            'two_factor_enabled' => false,
        ]);

        try {
            (new StagingDemoAdminSeeder())->run();
            $this->fail('Existing staging admin was unexpectedly modified.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already exists', $exception->getMessage());
        }

        $this->assertSame($originalHash, DB::table('admins')->where('email', 'staging.admin@example.test')->value('password'));
        $this->assertSame(1, DB::table('admins')->count());
    }

    public function test_it_requires_explicit_one_run_opt_in(): void
    {
        putenv('EDONATE_ALLOW_STAGING_DEMO_ADMIN');
        putenv('EDONATE_STAGING_DEMO_ADMIN_PASSWORD='.str_repeat('a', 48));

        try {
            (new StagingDemoAdminSeeder())->run();
            $this->fail('Provisioning without explicit opt-in was unexpectedly allowed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('explicit one-run opt-in', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('admins')->count());
    }

    private function allowTestProvisioning(): void
    {
        putenv('EDONATE_ALLOW_STAGING_DEMO_ADMIN=1');
        putenv('EDONATE_STAGING_DEMO_ADMIN_PASSWORD='.str_repeat('a', 48));
    }
}
