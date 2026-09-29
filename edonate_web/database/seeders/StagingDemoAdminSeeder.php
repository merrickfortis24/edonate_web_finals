<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class StagingDemoAdminSeeder extends Seeder
{
    private const EMAIL = 'staging.admin@example.test';

    private const USERNAME = 'staging_demo_admin';

    public function run(): void
    {
        $this->assertSafeTarget();

        $password = (string) getenv('EDONATE_STAGING_DEMO_ADMIN_PASSWORD');
        if (strlen($password) < 32 || strlen($password) > 72 || preg_match('/\s/', $password) === 1) {
            throw new RuntimeException('A strong staging demo-admin password is required; no admin record was changed.');
        }

        $requiredColumns = [
            'username', 'email', 'password', 'full_name', 'role', 'is_active',
            'auth_version', 'two_factor_enabled',
        ];
        if (! Schema::hasTable('admins') || ! Schema::hasColumns('admins', $requiredColumns)) {
            throw new RuntimeException('The staging admins table is incomplete; no admin record was changed.');
        }

        DB::transaction(function () use ($password): void {
            $existing = DB::table('admins')
                ->where('email', self::EMAIL)
                ->orWhere('username', self::USERNAME)
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw new RuntimeException('The staging demo-admin account already exists; its password was not changed.');
            }

            $now = now();
            $admin = [
                'username' => self::USERNAME,
                'email' => self::EMAIL,
                'password' => Hash::make($password),
                'full_name' => 'eDonate Staging Demo Admin',
                'role' => 'admin',
                'is_active' => true,
                'auth_version' => 0,
                'two_factor_enabled' => false,
            ];

            if (Schema::hasColumn('admins', 'created_at')) {
                $admin['created_at'] = $now;
            }
            if (Schema::hasColumn('admins', 'updated_at')) {
                $admin['updated_at'] = $now;
            }

            DB::table('admins')->insert($admin);
        });

        $this->command?->info('Created staging-only admin: '.self::EMAIL);
        $this->command?->info('The account must complete the existing Google Authenticator enrollment at first sign-in.');
        $this->command?->info('The password is intentionally never printed or stored in plaintext.');
    }

    private function assertSafeTarget(): void
    {
        if (getenv('EDONATE_ALLOW_STAGING_DEMO_ADMIN') !== '1') {
            throw new RuntimeException('Staging demo-admin provisioning requires explicit one-run opt-in.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        // Allow isolated SQLite tests, but never local SQLite, MySQL production,
        // or any other non-staging runtime to provision this privileged account.
        if (app()->environment('testing') && $driver === 'sqlite') {
            return;
        }

        $databaseName = (string) config('database.connections.'.$connection->getName().'.database');
        if (! app()->environment('staging') || $driver !== 'mysql'
            || preg_match('/(^|[_-])(stage|staging|test)([_-]|$)/i', $databaseName) !== 1) {
            throw new RuntimeException('Demo-admin provisioning is restricted to the isolated staging MySQL database.');
        }
    }
}
