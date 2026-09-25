<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'eligibility_status';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'result_reason')) {
                $table->text('result_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn(self::TABLE, 'recommendation_message')) {
                $table->text('recommendation_message')->nullable()->after('result_reason');
            }

            if (! Schema::hasColumn(self::TABLE, 'source')) {
                $table->enum('source', ['auto', 'admin_review'])->default('auto')->after('recommendation_message');
            }
        });

        $this->ensureStatusColumnSupportsAutomaticValues();
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['result_reason', 'recommendation_message', 'source'],
                fn (string $column): bool => Schema::hasColumn(self::TABLE, $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function ensureStatusColumnSupportsAutomaticValues(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn(self::TABLE, 'status')) {
            return;
        }

        $column = DB::selectOne(
            'SELECT DATA_TYPE, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [self::TABLE, 'status']
        );

        if (! $column || strtolower((string) $column->DATA_TYPE) !== 'enum') {
            return;
        }

        $values = $this->enumValues((string) $column->COLUMN_TYPE);
        foreach (['eligible', 'not_eligible', 'for_review', 'temporary_deferred', 'pending', 'approved', 'declined'] as $requiredValue) {
            if (! $this->containsEnumValue($values, $requiredValue)) {
                $values[] = $requiredValue;
            }
        }

        $enumSql = implode(',', array_map(
            fn (string $value): string => "'" . str_replace("'", "''", $value) . "'",
            $values
        ));

        DB::statement(
            "ALTER TABLE `" . self::TABLE . "` MODIFY `status` ENUM({$enumSql}) NULL"
        );
    }

    /**
     * @return array<int, string>
     */
    private function enumValues(string $columnType): array
    {
        if (! str_starts_with(strtolower($columnType), 'enum(')) {
            return [];
        }

        $values = str_getcsv(substr($columnType, 5, -1), ',', "'");

        return array_values(array_filter(
            array_map(fn (string $value): string => str_replace("''", "'", $value), $values),
            fn (string $value): bool => $value !== ''
        ));
    }

    /**
     * @param array<int, string> $values
     */
    private function containsEnumValue(array $values, string $needle): bool
    {
        foreach ($values as $value) {
            if (strtolower($value) === strtolower($needle)) {
                return true;
            }
        }

        return false;
    }
};
