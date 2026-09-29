<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'idx_blood_requests_request_source',
        'idx_blood_requests_requested_donor',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('blood_requests')) {
            return;
        }

        Schema::table('blood_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('blood_requests', 'request_source')) {
                $table->string('request_source', 30)->nullable();
            }
            if (! Schema::hasColumn('blood_requests', 'requested_by_donor_id')) {
                $table->integer('requested_by_donor_id')->nullable();
            }
            if (! Schema::hasColumn('blood_requests', 'reviewed_by_admin_id')) {
                $table->integer('reviewed_by_admin_id')->nullable();
            }
            if (! Schema::hasColumn('blood_requests', 'reviewed_at')) {
                $table->dateTime('reviewed_at')->nullable();
            }
            if (! Schema::hasColumn('blood_requests', 'review_reason')) {
                $table->text('review_reason')->nullable();
            }
        });

        $indexes = collect(Schema::getIndexes('blood_requests'))->pluck('name')->all();
        if (Schema::hasColumn('blood_requests', 'request_source') && ! in_array(self::INDEXES[0], $indexes, true)) {
            Schema::table('blood_requests', fn (Blueprint $table) => $table->index('request_source', self::INDEXES[0]));
        }

        if (Schema::hasColumn('blood_requests', 'requested_by_donor_id') && ! in_array(self::INDEXES[1], $indexes, true)) {
            Schema::table('blood_requests', fn (Blueprint $table) => $table->index('requested_by_donor_id', self::INDEXES[1]));
        }
    }

    public function down(): void
    {
        // These optional fields may already exist on installations whose mobile
        // API owns the original schema. Keep them and review history on rollback.
    }
};
