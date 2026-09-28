<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('donors') || ! Schema::hasTable('appointments') || ! Schema::hasTable('admins')) {
            throw new RuntimeException('The donors, appointments, and admins tables must exist before appointment restrictions are installed.');
        }

        // Legacy production keys can differ in signedness from fresh Laravel
        // migrations. Match new foreign-key columns to the deployed schema.
        $foreignKeyColumnTypes = [
            'donor_id' => $this->matchingIntegerColumnMethod('donors', 'donor_id'),
            'appointment_id' => $this->matchingIntegerColumnMethod('appointments', 'appointment_id'),
            'admin_id' => $this->matchingIntegerColumnMethod('admins', 'admin_id'),
        ];

        Schema::table('donors', function (Blueprint $table) use ($foreignKeyColumnTypes): void {
            if (! Schema::hasColumn('donors', 'appointment_restricted')) {
                $table->boolean('appointment_restricted')->default(false);
            }
            if (! Schema::hasColumn('donors', 'consecutive_cancellations')) {
                $table->unsignedSmallInteger('consecutive_cancellations')->default(0);
            }
            if (! Schema::hasColumn('donors', 'restriction_status')) {
                $table->string('restriction_status', 20)->default('clear');
            }
            if (! Schema::hasColumn('donors', 'restriction_reason')) {
                $table->text('restriction_reason')->nullable();
            }
            if (! Schema::hasColumn('donors', 'restricted_at')) {
                $table->dateTime('restricted_at')->nullable();
            }
            if (! Schema::hasColumn('donors', 'restricted_by')) {
                $table->{$foreignKeyColumnTypes['admin_id']}('restricted_by')->nullable();
                $table->foreign('restricted_by', 'fk_donors_appointment_restricted_by')
                    ->references('admin_id')->on('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('donors', 'restriction_lifted_at')) {
                $table->dateTime('restriction_lifted_at')->nullable();
            }
            if (! Schema::hasColumn('donors', 'restriction_lifted_by')) {
                $table->{$foreignKeyColumnTypes['admin_id']}('restriction_lifted_by')->nullable();
                $table->foreign('restriction_lifted_by', 'fk_donors_appointment_restriction_lifted_by')
                    ->references('admin_id')->on('admins')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('appointment_cancellations')) {
            Schema::create('appointment_cancellations', function (Blueprint $table) use ($foreignKeyColumnTypes): void {
                $table->increments('cancellation_id');
                $table->{$foreignKeyColumnTypes['donor_id']}('donor_id');
                $table->{$foreignKeyColumnTypes['appointment_id']}('appointment_id');
                $table->dateTime('cancelled_at');
                $table->text('reason');
                $table->string('cancelled_by', 20);
                $table->unsignedSmallInteger('consecutive_count');
                $table->timestamps();

                $table->foreign('donor_id', 'fk_appointment_cancellations_donor')
                    ->references('donor_id')->on('donors')->restrictOnDelete();
                $table->foreign('appointment_id', 'fk_appointment_cancellations_appointment')
                    ->references('appointment_id')->on('appointments')->restrictOnDelete();
                $table->index(['donor_id', 'cancelled_at'], 'idx_appointment_cancellations_donor_date');
            });
        }

        if (! Schema::hasTable('appointment_restrictions')) {
            Schema::create('appointment_restrictions', function (Blueprint $table) use ($foreignKeyColumnTypes): void {
                $table->increments('restriction_id');
                $table->{$foreignKeyColumnTypes['donor_id']}('donor_id');
                $table->string('status', 20)->default('active');
                $table->text('restriction_reason');
                $table->dateTime('restricted_at');
                $table->{$foreignKeyColumnTypes['admin_id']}('restricted_by')->nullable();
                $table->dateTime('lifted_at')->nullable();
                $table->{$foreignKeyColumnTypes['admin_id']}('lifted_by')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();

                $table->foreign('donor_id', 'fk_appointment_restrictions_donor')
                    ->references('donor_id')->on('donors')->restrictOnDelete();
                $table->foreign('restricted_by', 'fk_appointment_restrictions_created_by')
                    ->references('admin_id')->on('admins')->nullOnDelete();
                $table->foreign('lifted_by', 'fk_appointment_restrictions_lifted_by')
                    ->references('admin_id')->on('admins')->nullOnDelete();
                $table->index(['donor_id', 'status'], 'idx_appointment_restrictions_donor_status');
            });
        }

        if (! Schema::hasTable('appointment_restriction_appeals')) {
            Schema::create('appointment_restriction_appeals', function (Blueprint $table) use ($foreignKeyColumnTypes): void {
                $table->increments('appeal_id');
                $table->{$foreignKeyColumnTypes['donor_id']}('donor_id');
                $table->unsignedInteger('restriction_id');
                $table->text('justification');
                $table->string('status', 20)->default('pending');
                $table->dateTime('submitted_at');
                $table->dateTime('reviewed_at')->nullable();
                $table->{$foreignKeyColumnTypes['admin_id']}('reviewed_by')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();

                $table->foreign('donor_id', 'fk_appointment_appeals_donor')
                    ->references('donor_id')->on('donors')->restrictOnDelete();
                $table->foreign('restriction_id', 'fk_appointment_appeals_restriction')
                    ->references('restriction_id')->on('appointment_restrictions')->restrictOnDelete();
                $table->foreign('reviewed_by', 'fk_appointment_appeals_reviewer')
                    ->references('admin_id')->on('admins')->nullOnDelete();
                $table->index(['donor_id', 'status'], 'idx_appointment_appeals_donor_status');
                $table->index(['restriction_id', 'status'], 'idx_appointment_appeals_restriction_status');
            });
        }

        if (! Schema::hasTable('appointment_restriction_reviews')) {
            Schema::create('appointment_restriction_reviews', function (Blueprint $table) use ($foreignKeyColumnTypes): void {
                $table->increments('review_id');
                $table->unsignedInteger('restriction_id');
                $table->unsignedInteger('appeal_id')->nullable();
                $table->{$foreignKeyColumnTypes['admin_id']}('admin_id')->nullable();
                $table->string('action', 30);
                $table->text('notes')->nullable();
                $table->dateTime('reviewed_at');
                $table->timestamps();

                $table->foreign('restriction_id', 'fk_appointment_reviews_restriction')
                    ->references('restriction_id')->on('appointment_restrictions')->restrictOnDelete();
                $table->foreign('appeal_id', 'fk_appointment_reviews_appeal')
                    ->references('appeal_id')->on('appointment_restriction_appeals')->nullOnDelete();
                $table->foreign('admin_id', 'fk_appointment_reviews_admin')
                    ->references('admin_id')->on('admins')->nullOnDelete();
                $table->index(['restriction_id', 'reviewed_at'], 'idx_appointment_reviews_restriction_date');
            });
        }

        // A failed MySQL ALTER TABLE can leave the just-created table in place
        // without its foreign keys. Repair such partial installs in place so a
        // retry never needs to drop tables or discard cancellation history.
        $foreignKeys = [
            ['donors', 'restricted_by', 'admins', 'admin_id', 'fk_donors_appointment_restricted_by', 'null'],
            ['donors', 'restriction_lifted_by', 'admins', 'admin_id', 'fk_donors_appointment_restriction_lifted_by', 'null'],
            ['appointment_cancellations', 'donor_id', 'donors', 'donor_id', 'fk_appointment_cancellations_donor', 'restrict'],
            ['appointment_cancellations', 'appointment_id', 'appointments', 'appointment_id', 'fk_appointment_cancellations_appointment', 'restrict'],
            ['appointment_restrictions', 'donor_id', 'donors', 'donor_id', 'fk_appointment_restrictions_donor', 'restrict'],
            ['appointment_restrictions', 'restricted_by', 'admins', 'admin_id', 'fk_appointment_restrictions_created_by', 'null'],
            ['appointment_restrictions', 'lifted_by', 'admins', 'admin_id', 'fk_appointment_restrictions_lifted_by', 'null'],
            ['appointment_restriction_appeals', 'donor_id', 'donors', 'donor_id', 'fk_appointment_appeals_donor', 'restrict'],
            ['appointment_restriction_appeals', 'restriction_id', 'appointment_restrictions', 'restriction_id', 'fk_appointment_appeals_restriction', 'restrict'],
            ['appointment_restriction_appeals', 'reviewed_by', 'admins', 'admin_id', 'fk_appointment_appeals_reviewer', 'null'],
            ['appointment_restriction_reviews', 'restriction_id', 'appointment_restrictions', 'restriction_id', 'fk_appointment_reviews_restriction', 'restrict'],
            ['appointment_restriction_reviews', 'appeal_id', 'appointment_restriction_appeals', 'appeal_id', 'fk_appointment_reviews_appeal', 'null'],
            ['appointment_restriction_reviews', 'admin_id', 'admins', 'admin_id', 'fk_appointment_reviews_admin', 'null'],
        ];

        $this->matchExistingForeignKeyTypes($foreignKeys);
        $this->ensureMysqlForeignKeys($foreignKeys);

        $this->ensureMysqlIndexes([
            ['appointment_cancellations', ['donor_id', 'cancelled_at'], 'idx_appointment_cancellations_donor_date'],
            ['appointment_restrictions', ['donor_id', 'status'], 'idx_appointment_restrictions_donor_status'],
            ['appointment_restriction_appeals', ['donor_id', 'status'], 'idx_appointment_appeals_donor_status'],
            ['appointment_restriction_appeals', ['restriction_id', 'status'], 'idx_appointment_appeals_restriction_status'],
            ['appointment_restriction_reviews', ['restriction_id', 'reviewed_at'], 'idx_appointment_reviews_restriction_date'],
        ]);
    }

    private function matchingIntegerColumnMethod(string $table, string $column): string
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return 'unsignedInteger';
        }

        $metadata = $this->mysqlColumnMetadata($table, $column);
        $type = strtolower((string) ($metadata->data_type ?? ''));
        $unsigned = str_contains(strtolower((string) ($metadata->column_type ?? '')), 'unsigned');

        $methods = [
            'tinyint' => 'tinyInteger',
            'smallint' => 'smallInteger',
            'mediumint' => 'mediumInteger',
            'int' => 'integer',
            'integer' => 'integer',
            'bigint' => 'bigInteger',
        ];

        if (! isset($methods[$type])) {
            throw new RuntimeException("The {$table}.{$column} key must use an integer SQL type.");
        }

        return ($unsigned ? 'unsigned' : '').ucfirst($methods[$type]);
    }

    private function mysqlColumnMetadata(string $table, string $column): object
    {
        $metadata = DB::selectOne(
            'SELECT DATA_TYPE AS data_type, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable '
            .'FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        if (! $metadata) {
            throw new RuntimeException("Unable to inspect {$table}.{$column} for the appointment restriction migration.");
        }

        return $metadata;
    }

    private function matchExistingForeignKeyTypes(array $foreignKeys): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($foreignKeys as [$table, $column, $referencedTable, $referencedColumn]) {
            $parent = $this->mysqlColumnMetadata($referencedTable, $referencedColumn);
            $child = $this->mysqlColumnMetadata($table, $column);
            $parentType = $this->normalizedMysqlIntegerType($parent);
            $childType = $this->normalizedMysqlIntegerType($child);

            if ($parentType === $childType) {
                continue;
            }

            $nullable = strtoupper((string) $child->is_nullable) === 'YES' ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$parentType} {$nullable}");
        }
    }

    private function normalizedMysqlIntegerType(object $metadata): string
    {
        $types = [
            'tinyint' => 'TINYINT',
            'smallint' => 'SMALLINT',
            'mediumint' => 'MEDIUMINT',
            'int' => 'INT',
            'integer' => 'INT',
            'bigint' => 'BIGINT',
        ];
        $type = strtolower((string) ($metadata->data_type ?? ''));

        if (! isset($types[$type])) {
            throw new RuntimeException('Appointment restriction foreign keys must reference integer columns.');
        }

        return $types[$type].(str_contains(strtolower((string) ($metadata->column_type ?? '')), 'unsigned') ? ' UNSIGNED' : '');
    }

    private function ensureMysqlForeignKeys(array $foreignKeys): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($foreignKeys as [$table, $column, $referencedTable, $referencedColumn, $name, $deleteAction]) {
            $exists = DB::selectOne(
                'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS '
                .'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
                [$table, $name, 'FOREIGN KEY']
            );

            if ($exists) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable, $referencedColumn, $name, $deleteAction): void {
                $foreign = $blueprint->foreign($column, $name)->references($referencedColumn)->on($referencedTable);

                if ($deleteAction === 'null') {
                    $foreign->nullOnDelete();
                } else {
                    $foreign->restrictOnDelete();
                }
            });
        }
    }

    private function ensureMysqlIndexes(array $indexes): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($indexes as [$table, $columns, $name]) {
            $exists = DB::selectOne(
                'SELECT 1 FROM information_schema.STATISTICS '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$table, $name]
            );

            if ($exists) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                $blueprint->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_restriction_reviews');
        Schema::dropIfExists('appointment_restriction_appeals');
        Schema::dropIfExists('appointment_restrictions');
        Schema::dropIfExists('appointment_cancellations');

        if (Schema::hasTable('donors')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('donors', 'appointment_restricted') ? 'appointment_restricted' : null,
                Schema::hasColumn('donors', 'consecutive_cancellations') ? 'consecutive_cancellations' : null,
                Schema::hasColumn('donors', 'restriction_status') ? 'restriction_status' : null,
                Schema::hasColumn('donors', 'restriction_reason') ? 'restriction_reason' : null,
                Schema::hasColumn('donors', 'restricted_at') ? 'restricted_at' : null,
                Schema::hasColumn('donors', 'restricted_by') ? 'restricted_by' : null,
                Schema::hasColumn('donors', 'restriction_lifted_at') ? 'restriction_lifted_at' : null,
                Schema::hasColumn('donors', 'restriction_lifted_by') ? 'restriction_lifted_by' : null,
            ]));

            if ($columns !== []) {
                Schema::table('donors', function (Blueprint $table) use ($columns): void {
                    if (in_array('restricted_by', $columns, true)) {
                        $table->dropForeign('fk_donors_appointment_restricted_by');
                    }
                    if (in_array('restriction_lifted_by', $columns, true)) {
                        $table->dropForeign('fk_donors_appointment_restriction_lifted_by');
                    }
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
