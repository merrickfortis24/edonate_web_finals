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
            $table->unsignedInteger('donor_id')->nullable()->change();
        });

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
