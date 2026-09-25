<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('facility_blood_inventory')) {
            $this->addIndex('facility_blood_inventory', ['facility_id', 'blood_type_id'], 'uniq_phase10_facility_blood_type', true);
            $this->addIndex('facility_blood_inventory', ['last_updated'], 'idx_phase10_inventory_last_updated');
        }

        if (Schema::hasTable('facilities')) {
            $this->addIndex('facilities', ['facility_type', 'status'], 'idx_phase10_facility_type_status');
            $this->addIndex('facilities', ['city'], 'idx_phase10_facility_city');
        }

        if (! Schema::hasTable('facility_blood_inventory_logs')) {
            Schema::create('facility_blood_inventory_logs', function (Blueprint $table): void {
                $table->bigIncrements('inventory_log_id');
                $table->unsignedInteger('facility_id');
                $table->unsignedInteger('blood_type_id');
                $table->integer('previous_units')->default(0);
                $table->integer('new_units')->default(0);
                $table->integer('change_amount')->default(0);
                $table->string('action_type', 30)->default('set');
                $table->string('reason', 500);
                $table->integer('updated_by_admin_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['facility_id', 'created_at'], 'idx_phase10_inventory_log_facility');
                $table->index(['blood_type_id', 'created_at'], 'idx_phase10_inventory_log_blood_type');
                $table->foreign('facility_id')->references('facility_id')->on('facilities')->cascadeOnDelete();
                $table->foreign('blood_type_id')->references('blood_type_id')->on('blood_types');
                $table->foreign('updated_by_admin_id')->references('admin_id')->on('admins')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_blood_inventory_logs');
        $this->dropIndex('facility_blood_inventory', 'idx_phase10_inventory_last_updated');
        $this->dropIndex('facility_blood_inventory', 'uniq_phase10_facility_blood_type');
        $this->dropIndex('facilities', 'idx_phase10_facility_city');
        $this->dropIndex('facilities', 'idx_phase10_facility_type_status');
    }

    /** @param array<int, string> $columns */
    private function addIndex(string $tableName, array $columns, string $name, bool $unique = false): void
    {
        if ($this->hasIndex($tableName, $name) || ! $this->hasColumns($tableName, $columns)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $name, $unique): void {
            $unique ? $table->unique($columns, $name) : $table->index($columns, $name);
        });
    }

    private function dropIndex(string $tableName, string $name): void
    {
        if (!Schema::hasTable($tableName) || ! $this->hasIndex($tableName, $name)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($name): void {
            $table->dropIndex($name);
        });
    }

    /** @param array<int, string> $columns */
    private function hasColumns(string $tableName, array $columns): bool
    {
        if (!Schema::hasTable($tableName)) {
            return false;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($tableName, $column)) {
                return false;
            }
        }

        return true;
    }

    private function hasIndex(string $tableName, string $name): bool
    {
        if (!Schema::hasTable($tableName)) {
            return false;
        }

        foreach (Schema::getIndexes($tableName) as $index) {
            if (($index['name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }
};
