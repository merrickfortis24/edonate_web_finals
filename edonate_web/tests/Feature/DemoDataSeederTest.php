<?php

namespace Tests\Feature;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_dataset_covers_admin_workflows_and_is_idempotent(): void
    {
        Storage::fake('local');

        $this->seed(DemoDataSeeder::class);

        $this->assertSame(80, DB::table('donor_authentication')->where('email', 'like', 'demo.donor%@example.test')->count());
        $this->assertSame(5, DB::table('donors')->where('is_active', false)->count());
        $this->assertSame(10, DB::table('facilities')->where('facility_name', 'like', 'DEMO %')->count());
        $this->assertSame(14, DB::table('donation_events')->where('title', 'like', 'DEMO Event %')->count());
        $this->assertGreaterThan(100, DB::table('appointments')->where('donation_center', 'like', 'DEMO seed:%')->count());
        $this->assertGreaterThan(0, DB::table('donation_records')->where('remarks', 'like', 'DEMO %')->count());
        $this->assertSame(3, DB::table('blood_requests')
            ->where('request_source', 'app')
            ->where('status', 'pending_review')
            ->where('request_reference', 'like', 'DEMO-BR-%')
            ->count());
        $this->assertSame(1, DB::table('blood_requests')
            ->where('request_source', 'app')
            ->whereNull('facility_id')
            ->where('request_reference', 'like', 'DEMO-BR-%')
            ->count());
        $this->assertSame(2, DB::table('appointment_restrictions')
            ->where('status', 'active')
            ->where('restriction_reason', 'like', 'DEMO seed:%')
            ->count());
        $this->assertSame(1, DB::table('appointment_restriction_appeals')
            ->where('status', 'pending')
            ->where('justification', 'like', 'DEMO seed:%')
            ->count());
        $this->assertSame(0, DB::table('admins')->count());
        $this->assertSame(0, DB::table('admin_notifications')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());

        $this->seed(DemoDataSeeder::class);

        $this->assertSame(80, DB::table('donor_authentication')->where('email', 'like', 'demo.donor%@example.test')->count());
        $this->assertSame(14, DB::table('donation_events')->where('title', 'like', 'DEMO Event %')->count());
        $this->assertSame(3, DB::table('blood_requests')
            ->where('request_source', 'app')
            ->where('status', 'pending_review')
            ->where('request_reference', 'like', 'DEMO-BR-%')
            ->count());
        $this->assertSame(2, DB::table('appointment_restrictions')
            ->where('status', 'active')
            ->where('restriction_reason', 'like', 'DEMO seed:%')
            ->count());
    }

    public function test_staging_seed_requires_an_explicit_opt_in_and_mysql_database(): void
    {
        $previous = getenv('EDONATE_ALLOW_STAGING_DEMO_SEED');
        putenv('EDONATE_ALLOW_STAGING_DEMO_SEED=1');
        $_ENV['EDONATE_ALLOW_STAGING_DEMO_SEED'] = '1';
        $_SERVER['EDONATE_ALLOW_STAGING_DEMO_SEED'] = '1';
        $this->app->instance('env', 'staging');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Staging demo seeding requires a MySQL database name marked stage, staging, or test.');
            $this->seed(DemoDataSeeder::class);
        } finally {
            $this->app->instance('env', 'testing');
            if ($previous === false) {
                putenv('EDONATE_ALLOW_STAGING_DEMO_SEED');
                unset($_ENV['EDONATE_ALLOW_STAGING_DEMO_SEED'], $_SERVER['EDONATE_ALLOW_STAGING_DEMO_SEED']);
            } else {
                putenv('EDONATE_ALLOW_STAGING_DEMO_SEED='.$previous);
                $_ENV['EDONATE_ALLOW_STAGING_DEMO_SEED'] = $previous;
                $_SERVER['EDONATE_ALLOW_STAGING_DEMO_SEED'] = $previous;
            }
        }
    }
}
