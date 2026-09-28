<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('donors') || ! Schema::hasTable('appointments') || ! Schema::hasTable('admins')) {
            throw new RuntimeException('The donors, appointments, and admins tables must exist before appointment restrictions are installed.');
        }

        Schema::table('donors', function (Blueprint $table): void {
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
                $table->integer('restricted_by')->nullable();
                $table->foreign('restricted_by', 'fk_donors_appointment_restricted_by')
                    ->references('admin_id')->on('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('donors', 'restriction_lifted_at')) {
                $table->dateTime('restriction_lifted_at')->nullable();
            }
            if (! Schema::hasColumn('donors', 'restriction_lifted_by')) {
                $table->integer('restriction_lifted_by')->nullable();
                $table->foreign('restriction_lifted_by', 'fk_donors_appointment_restriction_lifted_by')
                    ->references('admin_id')->on('admins')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('appointment_cancellations')) {
            Schema::create('appointment_cancellations', function (Blueprint $table): void {
                $table->increments('cancellation_id');
                $table->unsignedInteger('donor_id');
                $table->unsignedInteger('appointment_id');
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
            Schema::create('appointment_restrictions', function (Blueprint $table): void {
                $table->increments('restriction_id');
                $table->unsignedInteger('donor_id');
                $table->string('status', 20)->default('active');
                $table->text('restriction_reason');
                $table->dateTime('restricted_at');
                $table->integer('restricted_by')->nullable();
                $table->dateTime('lifted_at')->nullable();
                $table->integer('lifted_by')->nullable();
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
            Schema::create('appointment_restriction_appeals', function (Blueprint $table): void {
                $table->increments('appeal_id');
                $table->unsignedInteger('donor_id');
                $table->unsignedInteger('restriction_id');
                $table->text('justification');
                $table->string('status', 20)->default('pending');
                $table->dateTime('submitted_at');
                $table->dateTime('reviewed_at')->nullable();
                $table->integer('reviewed_by')->nullable();
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
            Schema::create('appointment_restriction_reviews', function (Blueprint $table): void {
                $table->increments('review_id');
                $table->unsignedInteger('restriction_id');
                $table->unsignedInteger('appeal_id')->nullable();
                $table->integer('admin_id')->nullable();
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
