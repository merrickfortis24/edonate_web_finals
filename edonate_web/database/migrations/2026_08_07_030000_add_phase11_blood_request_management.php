<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blood_requests')) {
            Schema::create('blood_requests', function (Blueprint $table): void {
                $table->increments('request_id');
                $table->integer('facility_id')->nullable();
                $table->string('request_reference', 32)->nullable()->unique();
                $table->string('patient_reference_code', 100)->nullable();
                $table->string('request_type', 40)->default('replacement_donor');
                $table->integer('needed_blood_type_id')->nullable();
                $table->integer('required_donors')->default(1);
                $table->integer('total_donors_needed')->default(1);
                $table->integer('specific_match_required')->default(0);
                $table->integer('specific_blood_type_required_count')->default(0);
                $table->boolean('allow_other_blood_types')->default(false);
                $table->boolean('allow_any_blood_type_replacement')->default(false);
                $table->string('urgency', 20)->default('normal');
                $table->string('status', 30)->default('open');
                $table->text('notes')->nullable();
                $table->integer('created_by_admin_id')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('fulfilled_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->text('fulfillment_note')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
                $table->index(['facility_id', 'status'], 'idx_phase11_request_facility_status');
                $table->index(['needed_blood_type_id', 'status'], 'idx_phase11_request_blood_status');
                $table->index(['urgency', 'status'], 'idx_phase11_request_urgency_status');
                $table->index(['created_at'], 'idx_phase11_request_created');
            });
        } else {
            Schema::table('blood_requests', function (Blueprint $table): void {
                $this->addColumnIfMissing($table, 'request_reference', fn () => $table->string('request_reference', 32)->nullable());
                $this->addColumnIfMissing($table, 'request_type', fn () => $table->string('request_type', 40)->default('replacement_donor'));
                $this->addColumnIfMissing($table, 'required_donors', fn () => $table->integer('required_donors')->nullable());
                $this->addColumnIfMissing($table, 'specific_match_required', fn () => $table->integer('specific_match_required')->nullable());
                $this->addColumnIfMissing($table, 'allow_other_blood_types', fn () => $table->boolean('allow_other_blood_types')->default(false));
                $this->addColumnIfMissing($table, 'expires_at', fn () => $table->dateTime('expires_at')->nullable());
                $this->addColumnIfMissing($table, 'fulfilled_at', fn () => $table->dateTime('fulfilled_at')->nullable());
                $this->addColumnIfMissing($table, 'cancelled_at', fn () => $table->dateTime('cancelled_at')->nullable());
                $this->addColumnIfMissing($table, 'fulfillment_note', fn () => $table->text('fulfillment_note')->nullable());
                $this->addColumnIfMissing($table, 'cancellation_reason', fn () => $table->text('cancellation_reason')->nullable());
            });

            $this->widenMySqlColumn('blood_requests', 'status', "varchar(30) DEFAULT 'open'");
            $this->widenMySqlColumn('blood_requests', 'urgency', "varchar(20) DEFAULT 'normal'");
            $this->backfillRequestCompatibilityColumns();
            $this->addIndex('blood_requests', ['request_reference'], 'uniq_phase11_request_reference', true);
            $this->addIndex('blood_requests', ['facility_id', 'status'], 'idx_phase11_request_facility_status');
            $this->addIndex('blood_requests', ['needed_blood_type_id', 'status'], 'idx_phase11_request_blood_status');
            $this->addIndex('blood_requests', ['urgency', 'status'], 'idx_phase11_request_urgency_status');
            $this->addIndex('blood_requests', ['created_at'], 'idx_phase11_request_created');
        }

        if (! Schema::hasTable('blood_request_donors')) {
            Schema::create('blood_request_donors', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('request_id');
                $table->integer('donor_id');
                $table->string('match_type', 30)->default('replacement_any');
                $table->string('status', 30)->default('candidate');
                $table->dateTime('notified_at')->nullable();
                $table->dateTime('responded_at')->nullable();
                $table->timestamps();
                $table->unique(['request_id', 'donor_id'], 'uniq_phase11_request_donor');
                $table->index(['request_id', 'status'], 'idx_phase11_request_donor_status');
                $table->index(['donor_id', 'status'], 'idx_phase11_donor_request_status');
            });
        } else {
            Schema::table('blood_request_donors', function (Blueprint $table): void {
                $this->addColumnIfMissing($table, 'match_type', fn () => $table->string('match_type', 30)->default('replacement_any'));
                $this->addColumnIfMissing($table, 'notified_at', fn () => $table->dateTime('notified_at')->nullable());
                $this->addColumnIfMissing($table, 'responded_at', fn () => $table->dateTime('responded_at')->nullable());
                $this->addColumnIfMissing($table, 'updated_at', fn () => $table->timestamp('updated_at')->nullable());
            });

            $this->widenMySqlColumn('blood_request_donors', 'status', "varchar(30) DEFAULT 'candidate'");
            $this->addIndex('blood_request_donors', ['request_id', 'donor_id'], 'uniq_phase11_request_donor', true);
            $this->addIndex('blood_request_donors', ['request_id', 'status'], 'idx_phase11_request_donor_status');
            $this->addIndex('blood_request_donors', ['donor_id', 'status'], 'idx_phase11_donor_request_status');
        }
    }

    public function down(): void
    {
        $this->dropIndex('blood_request_donors', 'idx_phase11_donor_request_status');
        $this->dropIndex('blood_request_donors', 'idx_phase11_request_donor_status');
        $this->dropIndex('blood_request_donors', 'uniq_phase11_request_donor');
        $this->dropIndex('blood_requests', 'idx_phase11_request_created');
        $this->dropIndex('blood_requests', 'idx_phase11_request_urgency_status');
        $this->dropIndex('blood_requests', 'idx_phase11_request_blood_status');
        $this->dropIndex('blood_requests', 'idx_phase11_request_facility_status');
        $this->dropIndex('blood_requests', 'uniq_phase11_request_reference');
    }

    private function addColumnIfMissing(Blueprint $table, string $column, callable $definition): void
    {
        if (! Schema::hasColumn($table->getTable(), $column)) {
            $definition();
        }
    }

    private function widenMySqlColumn(string $table, string $column, string $definition): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            DB::statement("ALTER TABLE {$table} MODIFY {$column} {$definition}");
        } catch (Throwable) {
            //
        }
    }

    private function addIndex(string $table, array $columns, string $name, bool $unique = false): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name, $unique): void {
                $unique ? $blueprint->unique($columns, $name) : $blueprint->index($columns, $name);
            });
        } catch (Throwable) {
            //
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
        } catch (Throwable) {
            //
        }
    }

    private function backfillRequestCompatibilityColumns(): void
    {
        if (Schema::hasColumn('blood_requests', 'required_donors') && Schema::hasColumn('blood_requests', 'total_donors_needed')) {
            DB::table('blood_requests')->whereNull('required_donors')->update(['required_donors' => DB::raw('total_donors_needed')]);
        }

        if (Schema::hasColumn('blood_requests', 'specific_match_required') && Schema::hasColumn('blood_requests', 'specific_blood_type_required_count')) {
            DB::table('blood_requests')->whereNull('specific_match_required')->update(['specific_match_required' => DB::raw('specific_blood_type_required_count')]);
        }

        if (Schema::hasColumn('blood_requests', 'allow_other_blood_types') && Schema::hasColumn('blood_requests', 'allow_any_blood_type_replacement')) {
            DB::table('blood_requests')->whereNull('allow_other_blood_types')->update(['allow_other_blood_types' => DB::raw('allow_any_blood_type_replacement')]);
        }

        if (Schema::hasColumn('blood_requests', 'request_reference')) {
            DB::table('blood_requests')
                ->whereNull('request_reference')
                ->orderBy('request_id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        $type = (string) ($row->request_type ?? 'replacement_donor');
                        $prefix = $type === 'blood_request' ? 'BR' : 'RDR';
                        $year = $row->created_at ? date('Y', strtotime((string) $row->created_at)) : date('Y');
                        DB::table('blood_requests')
                            ->where('request_id', $row->request_id)
                            ->update(['request_reference' => sprintf('%s-%s-%06d', $prefix, $year, (int) $row->request_id)]);
                    }
                }, 'request_id');
        }
    }
};
