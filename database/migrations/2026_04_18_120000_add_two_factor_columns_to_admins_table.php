<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table): void {
            if (!Schema::hasColumn('admins', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false);
            }

            if (!Schema::hasColumn('admins', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable();
            }

            if (!Schema::hasColumn('admins', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable();
            }

            if (!Schema::hasColumn('admins', 'two_factor_recovery_codes')) {
                $table->json('two_factor_recovery_codes')->nullable();
            }

            if (!Schema::hasColumn('admins', 'two_factor_last_verified_at')) {
                $table->timestamp('two_factor_last_verified_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table): void {
            $dropColumns = [];

            foreach ([
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
                'two_factor_last_verified_at',
            ] as $column) {
                if (Schema::hasColumn('admins', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
