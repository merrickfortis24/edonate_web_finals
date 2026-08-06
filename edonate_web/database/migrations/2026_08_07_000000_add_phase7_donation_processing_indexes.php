<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table): void {
                if (Schema::hasColumn('appointments', 'status')
                    && Schema::hasColumn('appointments', 'appointment_date')
                    && ! $this->hasIndex('appointments', 'idx_phase7_appointments_status_date')) {
                    $table->index(['status', 'appointment_date'], 'idx_phase7_appointments_status_date');
                }

                if (Schema::hasColumn('appointments', 'checked_in_at')
                    && ! $this->hasIndex('appointments', 'idx_phase7_appointments_checkin')) {
                    $table->index(['checked_in_at'], 'idx_phase7_appointments_checkin');
                }
            });
        }

        if (Schema::hasTable('donation_records')) {
            Schema::table('donation_records', function (Blueprint $table): void {
                if (Schema::hasColumn('donation_records', 'donation_status')
                    && Schema::hasColumn('donation_records', 'donation_date')
                    && ! $this->hasIndex('donation_records', 'idx_phase7_donation_status_date')) {
                    $table->index(['donation_status', 'donation_date'], 'idx_phase7_donation_status_date');
                }

                if (Schema::hasColumn('donation_records', 'recorded_by_admin_id')
                    && ! $this->hasIndex('donation_records', 'idx_phase7_donation_recorded_by')) {
                    $table->index(['recorded_by_admin_id'], 'idx_phase7_donation_recorded_by');
                }
            });

            if (Schema::hasColumn('donation_records', 'appointment_id')
                && ! $this->hasDuplicateAppointmentRecords()
                && ! $this->hasIndex('donation_records', 'uniq_phase7_donation_records_appointment')) {
                Schema::table('donation_records', function (Blueprint $table): void {
                    $table->unique('appointment_id', 'uniq_phase7_donation_records_appointment');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('donation_records')) {
            Schema::table('donation_records', function (Blueprint $table): void {
                $this->dropIndexIfExists($table, 'donation_records', 'uniq_phase7_donation_records_appointment');
                $this->dropIndexIfExists($table, 'donation_records', 'idx_phase7_donation_recorded_by');
                $this->dropIndexIfExists($table, 'donation_records', 'idx_phase7_donation_status_date');
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $this->dropIndexIfExists($table, 'appointments', 'idx_phase7_appointments_checkin');
                $this->dropIndexIfExists($table, 'appointments', 'idx_phase7_appointments_status_date');
            });
        }
    }

    private function hasDuplicateAppointmentRecords(): bool
    {
        return DB::table('donation_records')
            ->whereNotNull('appointment_id')
            ->select('appointment_id')
            ->groupBy('appointment_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $indexName): void
    {
        if ($this->hasIndex($tableName, $indexName)) {
            $table->dropIndex($indexName);
        }
    }
};
