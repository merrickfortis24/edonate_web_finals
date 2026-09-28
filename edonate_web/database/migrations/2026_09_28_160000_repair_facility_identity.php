<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facilities')) {
            return;
        }

        // Imported Hostinger tables may have lost their key/identity attributes.
        // Retain the original signedness, IDs, foreign keys and all existing rows.
        if (DB::getDriverName() === 'mysql') {
            $column = DB::selectOne("SHOW COLUMNS FROM `facilities` LIKE 'facility_id'");
            if ($column && ! str_contains(strtolower($column->Extra), 'auto_increment')) {
                $invalid = DB::table('facilities')->whereNull('facility_id')->orWhere('facility_id', '<=', 0)->exists();
                $duplicates = DB::table('facilities')->select('facility_id')->groupBy('facility_id')
                    ->havingRaw('COUNT(*) > 1')->exists();
                if ($invalid || $duplicates) {
                    throw new RuntimeException('Facility identity repair stopped: review duplicate or non-positive facility IDs. No records were changed.');
                }
                if (! preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)(?:\(\d+\))?(?: unsigned)?$/i', $column->Type)) {
                    throw new RuntimeException('Facility identity repair stopped: unsupported facility_id column type.');
                }

                $indexes = collect(Schema::getIndexes('facilities'));
                $hasIdentityKey = $indexes->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['facility_id']);
                $hasPrimaryKey = $indexes->contains(fn (array $index): bool => ($index['primary'] ?? false) === true);
                $key = $hasIdentityKey ? '' : ($hasPrimaryKey
                    ? ', ADD UNIQUE KEY `uq_facility_identity` (`facility_id`)'
                    : ', ADD PRIMARY KEY (`facility_id`)');
                DB::statement('ALTER TABLE `facilities` MODIFY `facility_id` '.$column->Type.' NOT NULL AUTO_INCREMENT'.$key);
            }
        }
    }

    public function down(): void
    {
        // Do not undo the identity repair or renumber existing facilities.
    }
};
