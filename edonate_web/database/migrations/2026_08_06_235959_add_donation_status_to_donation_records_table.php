<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('donation_records')) {
            return;
        }

        $addedColumn = false;

        if (! Schema::hasColumn('donation_records', 'donation_status')) {
            Schema::table('donation_records', function (Blueprint $table): void {
                $table->string('donation_status', 20)
                    ->default('pending')
                    ->after('appointment_id');
            });

            $addedColumn = true;
        }

        /*
         * Preserve the meaning of old donation records.
         * Existing records with a donation date were effectively completed
         * before donation_status existed.
         */
        if ($addedColumn) {
            DB::table('donation_records')
                ->whereNotNull('donation_date')
                ->update([
                    'donation_status' => 'completed',
                ]);

            DB::table('donation_records')
                ->whereNull('donation_date')
                ->whereRaw("LOWER(COALESCE(remarks, '')) LIKE ?", ['%defer%'])
                ->update([
                    'donation_status' => 'deferred',
                ]);
        }

        if (
            Schema::hasColumn('donation_records', 'donation_status') &&
            Schema::hasColumn('donation_records', 'donation_date') &&
            ! $this->hasIndex(
                'donation_records',
                'idx_phase7_donation_status_date'
            )
        ) {
            Schema::table('donation_records', function (Blueprint $table): void {
                $table->index(
                    ['donation_status', 'donation_date'],
                    'idx_phase7_donation_status_date'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('donation_records')) {
            return;
        }

        if ($this->hasIndex(
            'donation_records',
            'idx_phase7_donation_status_date'
        )) {
            Schema::table('donation_records', function (Blueprint $table): void {
                $table->dropIndex('idx_phase7_donation_status_date');
            });
        }

        if (Schema::hasColumn('donation_records', 'donation_status')) {
            Schema::table('donation_records', function (Blueprint $table): void {
                $table->dropColumn('donation_status');
            });
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        try {
            foreach (Schema::getIndexes($table) as $index) {
                if (($index['name'] ?? '') === $name) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
};