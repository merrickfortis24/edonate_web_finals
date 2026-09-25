<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('donors', ['verification_status', 'blood_type_status', 'location_id'], 'idx_phase9_donor_availability');
        $this->addIndex('eligibility_status', ['donor_id', 'eligibility_id'], 'idx_phase9_eligibility_latest');
        $this->addIndex('locations', ['barangay_code', 'city'], 'idx_phase9_location_barangay');
    }

    public function down(): void
    {
        $this->dropIndex('locations', 'idx_phase9_location_barangay');
        $this->dropIndex('eligibility_status', 'idx_phase9_eligibility_latest');
        $this->dropIndex('donors', 'idx_phase9_donor_availability');
    }

    /** @param array<int, string> $columns */
    private function addIndex(string $tableName, array $columns, string $indexName): void
    {
        if (!Schema::hasTable($tableName) || ! $this->hasColumns($tableName, $columns) || $this->hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndex(string $tableName, string $indexName): void
    {
        if (!Schema::hasTable($tableName) || ! $this->hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    /** @param array<int, string> $columns */
    private function hasColumns(string $tableName, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn($tableName, $column)) {
                return false;
            }
        }

        return true;
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        foreach (Schema::getIndexes($tableName) as $index) {
            if (($index['name'] ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }
};
