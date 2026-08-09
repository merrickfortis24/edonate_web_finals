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

        if (! Schema::hasColumn('appointments', 'event_id')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->integer('event_id')->nullable()->after('donor_id');
            });
        }

        if (! Schema::hasColumn('appointments', 'checked_in_at')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->dateTime('checked_in_at')->nullable()->after('completed_at');
            });
        }

        if (! Schema::hasColumn('appointments', 'cancellation_reason')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->text('cancellation_reason')->nullable()->after('checked_in_at');
            });
        }

        if (! Schema::hasColumn('appointments', 'donation_center')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->string('donation_center', 100)->nullable()->after('admin_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('appointments')) {
            return;
        }

        foreach ([
            'donation_center',
            'cancellation_reason',
            'checked_in_at',
            'event_id',
        ] as $column) {
            if (Schema::hasColumn('appointments', $column)) {
                Schema::table('appointments', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};