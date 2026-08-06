<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('donation_events')) {
            Schema::table('donation_events', function (Blueprint $table): void {
                if (! Schema::hasColumn('donation_events', 'description')) {
                    $table->text('description')->nullable()->after('title');
                }

                if (! Schema::hasColumn('donation_events', 'latitude')) {
                    $table->decimal('latitude', 10, 6)->nullable()->after('address');
                }

                if (! Schema::hasColumn('donation_events', 'longitude')) {
                    $table->decimal('longitude', 10, 6)->nullable()->after('latitude');
                }

                if (! Schema::hasColumn('donation_events', 'blood_types_needed')) {
                    $table->text('blood_types_needed')->nullable()->after('max_capacity');
                }
            });

            if (DB::getDriverName() === 'mysql' && Schema::hasColumn('donation_events', 'status')) {
                DB::statement("ALTER TABLE donation_events MODIFY status ENUM('open','closed','upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming'");
            }

            if (! $this->hasIndex('donation_events', 'idx_donation_events_status_date')) {
                Schema::table('donation_events', function (Blueprint $table): void {
                    $table->index(['status', 'event_date'], 'idx_donation_events_status_date');
                });
            }
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table): void {
                if (! Schema::hasColumn('appointments', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }
            });

            if (! $this->hasIndex('appointments', 'idx_appointments_event_status_date')) {
                Schema::table('appointments', function (Blueprint $table): void {
                    $table->index(['event_id', 'status', 'appointment_date'], 'idx_appointments_event_status_date');
                });
            }

            if (! $this->hasIndex('appointments', 'idx_appointments_donor_status_date')) {
                Schema::table('appointments', function (Blueprint $table): void {
                    $table->index(['donor_id', 'status', 'appointment_date'], 'idx_appointments_donor_status_date');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table): void {
                if ($this->hasIndex('appointments', 'idx_appointments_event_status_date')) {
                    $table->dropIndex('idx_appointments_event_status_date');
                }

                if ($this->hasIndex('appointments', 'idx_appointments_donor_status_date')) {
                    $table->dropIndex('idx_appointments_donor_status_date');
                }

                if (Schema::hasColumn('appointments', 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
            });
        }

        if (Schema::hasTable('donation_events')) {
            if (DB::getDriverName() === 'mysql' && Schema::hasColumn('donation_events', 'status')) {
                DB::table('donation_events')
                    ->whereIn('status', ['upcoming', 'ongoing'])
                    ->update(['status' => 'open']);

                DB::statement("ALTER TABLE donation_events MODIFY status ENUM('open','closed','cancelled','completed') DEFAULT 'open'");
            }

            Schema::table('donation_events', function (Blueprint $table): void {
                if ($this->hasIndex('donation_events', 'idx_donation_events_status_date')) {
                    $table->dropIndex('idx_donation_events_status_date');
                }

                foreach (['description', 'latitude', 'longitude', 'blood_types_needed'] as $column) {
                    if (Schema::hasColumn('donation_events', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        try {
            foreach (Schema::getIndexes($table) as $index) {
                if (($index['name'] ?? '') === $name) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
};
