<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('donor_verifications')) {
            return;
        }

        Schema::table('donor_verifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('donor_verifications', 'document_preview_path')) {
                $table->string('document_preview_path', 255)->nullable();
            }

            if (! Schema::hasColumn('donor_verifications', 'document_back_preview_path')) {
                $table->string('document_back_preview_path', 255)->nullable();
            }
        });

        if (! Schema::hasColumn('donor_verifications', 'donor_id')) {
            return;
        }

        // Preserve existing submissions if their donor row is later removed.
        // Drop only the donor_id -> donors foreign key; leave reviewer relations intact.
        $donorForeignKeys = array_values(array_filter(
            Schema::getForeignKeys('donor_verifications'),
            fn (array $foreignKey): bool => ($foreignKey['columns'] ?? []) === ['donor_id']
                && ($foreignKey['foreign_table'] ?? null) === 'donors'
        ));

        Schema::table('donor_verifications', function (Blueprint $table) use ($donorForeignKeys): void {
            foreach ($donorForeignKeys as $foreignKey) {
                // SQLite handles foreign-key removal during the same table rebuild
                // as the nullable column change, and accepts column names not names.
                $dropTarget = DB::getDriverName() === 'sqlite'
                    ? $foreignKey['columns']
                    : $foreignKey['name'];
                $table->dropForeign($dropTarget);
            }
        });

        if (DB::getDriverName() === 'mysql' && Schema::hasTable('donors') && Schema::hasColumn('donors', 'donor_id')) {
            // Imported production schemas do not always use the same integer
            // signedness/width as the Laravel-created schema. MySQL requires
            // both sides of a foreign key to have identical column types, so
            // make the nullable child column match the real donor key exactly.
            $donorKey = DB::selectOne(
                'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['donors', 'donor_id']
            );
            $columnType = strtolower((string) ($donorKey->COLUMN_TYPE ?? ''));

            if (! preg_match('/\\A(?:tinyint|smallint|mediumint|int|integer|bigint)(?:\\(\\d+\\))?(?: unsigned)?\\z/', $columnType)) {
                throw new RuntimeException('Unable to safely determine the donors.donor_id integer type.');
            }

            DB::statement("ALTER TABLE `donor_verifications` MODIFY `donor_id` {$columnType} NULL");
        } else {
            Schema::table('donor_verifications', function (Blueprint $table): void {
                $table->integer('donor_id')->nullable()->change();
            });
        }

        // Do not rewrite historical donor IDs. If a legacy database already has
        // orphaned references, keep them intact and visible for manual reconciliation.
        if (Schema::hasTable('donors') && ! DB::table('donor_verifications as dv')
            ->leftJoin('donors as d', 'd.donor_id', '=', 'dv.donor_id')
            ->whereNotNull('dv.donor_id')
            ->whereNull('d.donor_id')
            ->exists()) {
            Schema::table('donor_verifications', function (Blueprint $table): void {
                $table->foreign('donor_id', 'donor_verifications_donor_id_foreign')
                    ->references('donor_id')
                    ->on('donors')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Keep the nullable donor link and cached previews on rollback: both can
        // protect historical submissions and are referenced by uploaded files.
    }
};
