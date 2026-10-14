<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointments')) {
            return;
        }

        if (! Schema::hasColumn('appointments', 'rejection_reason')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->text('rejection_reason')->nullable();
            });
        }

        if (! Schema::hasColumn('appointments', 'rejected_by_admin_id')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->integer('rejected_by_admin_id')->nullable();
            });
        }

        if (! Schema::hasColumn('appointments', 'rejected_at')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->dateTime('rejected_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('appointments')) {
            return;
        }

        $columns = array_values(array_filter(
            ['rejection_reason', 'rejected_by_admin_id', 'rejected_at'],
            static fn (string $column): bool => Schema::hasColumn('appointments', $column)
        ));

        if ($columns !== []) {
            Schema::table('appointments', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
