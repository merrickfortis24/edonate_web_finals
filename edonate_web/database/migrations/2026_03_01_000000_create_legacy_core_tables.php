<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provide the legacy eDonate tables required by later feature migrations.
 *
 * Existing installations are left untouched: each table is created only when
 * absent. The admin key is a signed INT to match the imported production
 * schema and the existing admin-device/document-verification constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $adminId = $table->integer('admin_id')->autoIncrement();
                if (DB::getDriverName() !== 'sqlite') {
                    $adminId->primary();
                }
                $table->string('username', 100)->unique();
                $table->string('email', 150)->nullable()->unique();
                $table->string('password');
                $table->string('full_name', 150)->nullable();
                $table->string('role', 30)->default('Staff');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('blood_types')) {
            Schema::create('blood_types', function (Blueprint $table): void {
                $table->increments('blood_type_id');
                $table->string('blood_type', 5)->unique();
            });
        }

        if (! Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table): void {
                $table->increments('location_id');
                $table->string('street_address')->nullable();
                $table->string('barangay_code', 30)->nullable();
                $table->string('barangay_name', 100)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('province', 100)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->index(['barangay_code', 'city'], 'idx_locations_barangay_city');
            });
        }

        if (! Schema::hasTable('donors')) {
            Schema::create('donors', function (Blueprint $table): void {
                $table->increments('donor_id');
                $table->string('first_name', 100)->nullable();
                $table->string('middle_initial', 20)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('suffix', 20)->nullable();
                $table->string('gender', 30)->nullable();
                $table->date('birthdate')->nullable();
                $table->string('contact_number', 40)->nullable();
                $table->unsignedInteger('blood_type_id')->nullable();
                $table->string('blood_type_status', 30)->nullable();
                $table->integer('blood_type_verified_by_admin_id')->nullable();
                $table->timestamp('blood_type_verified_at')->nullable();
                $table->unsignedInteger('location_id')->nullable();
                $table->timestamp('date_registered')->nullable();
            });
        }

        if (! Schema::hasTable('donor_authentication')) {
            Schema::create('donor_authentication', function (Blueprint $table): void {
                $table->increments('auth_id');
                $table->unsignedInteger('donor_id');
                $table->string('email', 150)->unique();
                $table->string('password')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->string('verification_token', 255)->nullable();
                $table->timestamp('verification_sent_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
                $table->index('donor_id', 'idx_donor_authentication_donor');
            });
        }

        if (! Schema::hasTable('eligibility_status')) {
            Schema::create('eligibility_status', function (Blueprint $table): void {
                $table->increments('eligibility_id');
                $table->unsignedInteger('donor_id');
                $table->date('last_donation_date')->nullable();
                $table->date('next_eligible_date')->nullable();
                $table->string('status', 40)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('screening_questions')) {
            Schema::create('screening_questions', function (Blueprint $table): void {
                $table->increments('question_id');
                $table->string('question_text', 500);
                $table->string('followup_prompt', 500)->nullable();
                $table->string('followup_trigger', 10)->nullable();
                $table->integer('question_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('extra_data')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('donor_screening_answers')) {
            Schema::create('donor_screening_answers', function (Blueprint $table): void {
                $table->increments('answer_id');
                $table->unsignedInteger('eligibility_id');
                $table->unsignedInteger('question_id');
                $table->text('answer')->nullable();
                $table->text('followup_answer')->nullable();
                $table->index(['eligibility_id', 'question_id'], 'idx_screening_answers_eligibility_question');
            });
        }

        if (! Schema::hasTable('facilities')) {
            Schema::create('facilities', function (Blueprint $table): void {
                $table->increments('facility_id');
                $table->string('facility_name', 150);
                $table->string('facility_type', 40)->nullable();
                $table->text('address')->nullable();
                $table->string('barangay_name', 100)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('province', 100)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('contact_number', 40)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('donation_events')) {
            Schema::create('donation_events', function (Blueprint $table): void {
                $table->increments('event_id');
                $table->string('title', 150);
                $table->date('event_date');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->string('location_name', 150)->nullable();
                $table->text('address')->nullable();
                $table->unsignedInteger('max_capacity')->default(100);
                $table->string('status', 30)->default('open');
                $table->integer('created_by_admin_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table): void {
                $table->increments('appointment_id');
                $table->unsignedInteger('donor_id')->nullable();
                $table->date('appointment_date')->nullable();
                $table->time('appointment_time')->nullable();
                $table->string('status', 50)->default('confirmed');
                $table->integer('admin_id')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('donation_records')) {
            Schema::create('donation_records', function (Blueprint $table): void {
                $table->increments('donation_id');
                $table->unsignedInteger('donor_id')->nullable();
                $table->unsignedInteger('appointment_id')->nullable();
                $table->date('donation_date')->nullable();
                $table->integer('blood_units')->nullable();
                $table->unsignedInteger('verified_blood_type_id')->nullable();
                $table->text('remarks')->nullable();
                $table->text('deferred_reason')->nullable();
                $table->integer('recorded_by_admin_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->increments('notification_id');
                $table->unsignedInteger('donor_id');
                $table->text('message');
                $table->string('notification_type', 50)->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->nullable();
                $table->boolean('push_sent')->default(false);
                $table->index(['donor_id', 'is_read'], 'idx_notifications_donor_read');
            });
        }

        if (! Schema::hasTable('facility_blood_inventory')) {
            Schema::create('facility_blood_inventory', function (Blueprint $table): void {
                $table->increments('inventory_id');
                $table->unsignedInteger('facility_id');
                $table->unsignedInteger('blood_type_id');
                $table->integer('available_units')->default(0);
                $table->integer('reserved_units')->default(0);
                $table->integer('low_stock_threshold')->default(5);
                $table->timestamp('last_updated')->nullable();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    /**
     * Core operational tables may already contain production data, so this
     * compatibility baseline deliberately never drops them during rollback.
     */
    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
