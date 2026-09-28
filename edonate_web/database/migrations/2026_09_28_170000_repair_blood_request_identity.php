<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    private const SUBMISSION_INDEX = 'uniq_blood_requests_submission_key';

    public function up(): void
    {
        if (! Schema::hasTable('blood_requests')) {
            return;
        }

        if (! Schema::hasColumn('blood_requests', 'submission_key')) {
            Schema::table('blood_requests', function (Blueprint $table): void {
                $table->string('submission_key', 36)->nullable();
            });
        }

        $this->repairMysqlRequestId();
        $this->ensureSubmissionKeyIndex();
    }

    public function down(): void
    {
        if (! Schema::hasTable('blood_requests') || ! Schema::hasColumn('blood_requests', 'submission_key')) {
            return;
        }

        $hasIndex = collect(Schema::getIndexes('blood_requests'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === self::SUBMISSION_INDEX);

        Schema::table('blood_requests', function (Blueprint $table) use ($hasIndex): void {
            if ($hasIndex) {
                $table->dropUnique(self::SUBMISSION_INDEX);
            }
            $table->dropColumn('submission_key');
        });

        // Keep the request_id primary/auto-increment repair: reverting it could
        // make existing BloodRequest records impossible to address safely.
    }

    private function repairMysqlRequestId(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $column = DB::selectOne(
            'SELECT COLUMN_TYPE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['blood_requests', 'request_id']
        );

        if (! $column) {
            throw new \RuntimeException('Cannot repair blood_requests: request_id column is missing.');
        }

        $columnType = (string) $column->COLUMN_TYPE;
        if (! preg_match('/\A(?:tinyint|smallint|mediumint|int|integer|bigint)(?:\(\d+\))?(?: unsigned)?\z/i', $columnType)) {
            throw new \RuntimeException('Cannot safely repair blood_requests.request_id because its SQL type is unsupported.');
        }

        $primaryColumns = collect(DB::select("SHOW INDEX FROM `blood_requests` WHERE `Key_name` = 'PRIMARY'"))
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->values()
            ->all();

        if ($primaryColumns !== [] && $primaryColumns !== ['request_id']) {
            throw new \RuntimeException('Cannot safely repair blood_requests.request_id because another primary key is already defined.');
        }

        $isAutoIncrement = str_contains(strtolower((string) $column->EXTRA), 'auto_increment');
        if ($primaryColumns === ['request_id'] && $isAutoIncrement) {
            return;
        }

        $identityStats = DB::selectOne(
            'SELECT COUNT(*) AS total, COUNT(request_id) AS present, COUNT(DISTINCT request_id) AS distinct_ids FROM blood_requests'
        );

        if ((int) $identityStats->total !== (int) $identityStats->present
            || (int) $identityStats->total !== (int) $identityStats->distinct_ids) {
            throw new \RuntimeException('Cannot safely repair blood_requests.request_id because existing identifiers are null or duplicated.');
        }

        $alterColumn = "MODIFY `request_id` {$columnType} NOT NULL AUTO_INCREMENT";
        if ($primaryColumns === []) {
            DB::statement("ALTER TABLE `blood_requests` {$alterColumn}, ADD PRIMARY KEY (`request_id`)");
            return;
        }

        DB::statement("ALTER TABLE `blood_requests` {$alterColumn}");
    }

    private function ensureSubmissionKeyIndex(): void
    {
        $hasIndex = collect(Schema::getIndexes('blood_requests'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === self::SUBMISSION_INDEX);

        if ($hasIndex) {
            return;
        }

        $duplicateKeys = DB::table('blood_requests')
            ->whereNotNull('submission_key')
            ->select('submission_key')
            ->groupBy('submission_key')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateKeys) {
            throw new \RuntimeException('Cannot safely add the blood request submission index because existing keys are duplicated.');
        }

        Schema::table('blood_requests', function (Blueprint $table): void {
            $table->unique('submission_key', self::SUBMISSION_INDEX);
        });
    }
};
