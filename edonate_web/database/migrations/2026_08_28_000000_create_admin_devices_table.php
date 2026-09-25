<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store browser push subscriptions used by admin number-matching MFA.
     *
     * The existing eDonate installation uses an `admins` table with an
     * `admin_id` primary key rather than Laravel's default `users` table.
     * `user_id` is therefore intentionally the admin id in this table, while
     * retaining the requested generic column name for the device contract.
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_devices')) {
            return;
        }

        Schema::create('admin_devices', function (Blueprint $table): void {
            $table->bigIncrements('admin_device_id');
            $table->integer('user_id')->index();
            $table->text('endpoint');
            $table->string('public_key', 512);
            $table->string('auth_token', 512);
            $table->timestamps();
        });

        // The imported production schema defines admins.admin_id as a signed
        // INT, so the device FK uses a signed INT as well. Keeping this
        // conditional makes the migration safe for a fresh app boot before
        // the externally supplied admin schema has been imported.
        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'admin_id')) {
            Schema::table('admin_devices', function (Blueprint $table): void {
                $table->foreign('user_id', 'admin_devices_user_id_foreign')
                    ->references('admin_id')
                    ->on('admins')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_devices');
    }
};
