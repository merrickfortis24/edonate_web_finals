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

        Schema::table('appointments', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointments', 'event_id')) {
                $table->integer('event_id')->nullable()->after('donor_id');
            }

            if (! Schema::hasColumn('appointments', 'checked_in_at')) {
                $table->dateTime('checked_in_at')->nullable()->after('completed_at');
            }

            if (! Schema::hasColumn('appointments', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('checked_in_at');
            }

            if (! Schema::hasColumn('appointments', 'donation_center')) {
                $table->string('donation_center', 100)->nullable()->after('admin_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table): void {
            $columns = [];

            foreach ([
                'event_id',
                'checked_in_at',
                'cancellation_reason',
                'donation_center',
            ] as $column) {
                if (Schema::hasColumn('appointments', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};