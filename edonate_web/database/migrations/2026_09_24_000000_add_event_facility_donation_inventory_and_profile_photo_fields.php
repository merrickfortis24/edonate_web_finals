<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('donation_events')) {
            if (! Schema::hasColumn('donation_events', 'facility_id')) {
                Schema::table('donation_events', function (Blueprint $table): void {
                    $table->unsignedInteger('facility_id')->nullable()->after('location_name');
                });
            }

            // Existing Hostinger facilities tables predate this migration and
            // may use an incompatible key type/engine. Keep the relationship
            // at the application layer until those legacy keys are normalized.
            if (! Schema::hasIndex('donation_events', 'idx_event_facility_date')) {
                Schema::table('donation_events', function (Blueprint $table): void {
                    $table->index(['facility_id', 'event_date'], 'idx_event_facility_date');
                });
            }
        }

        if (Schema::hasTable('donation_records') && ! Schema::hasColumn('donation_records', 'inventory_received_at')) {
            Schema::table('donation_records', function (Blueprint $table): void {
                $table->timestamp('inventory_received_at')->nullable()->after('blood_units');
            });
        }

        if (Schema::hasTable('donors') && ! Schema::hasColumn('donors', 'profile_photo_path')) {
            Schema::table('donors', function (Blueprint $table): void {
                $table->string('profile_photo_path', 255)->nullable();
            });
        }

        if (Schema::hasTable('facility_blood_inventory_logs')) {
            Schema::table('facility_blood_inventory_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('facility_blood_inventory_logs', 'related_donation_id')) {
                    $table->unsignedInteger('related_donation_id')->nullable();
                    $table->unique('related_donation_id', 'uq_inventory_log_donation');
                }
                if (! Schema::hasColumn('facility_blood_inventory_logs', 'related_blood_request_id')) {
                    $table->unsignedInteger('related_blood_request_id')->nullable();
                    $table->index('related_blood_request_id', 'idx_inventory_log_blood_request');
                }
            });
        }
    }

    /**
     * Deliberately retain nullable rollout data on rollback. Removing event
     * links, inventory receipt markers, or uploaded-photo paths would discard
     * operational data; deploy a reviewed data migration if rollback is needed.
     */
    public function down(): void
    {
        // Non-destructive by design.
    }
};
