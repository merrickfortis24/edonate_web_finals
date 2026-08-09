<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store per-admin notification delivery preferences outside protected
     * admin/security tables.
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_notification_preferences')) {
            return;
        }

        Schema::create('admin_notification_preferences', function (Blueprint $table): void {
            $table->bigIncrements('admin_notification_preference_id');
            $table->unsignedInteger('admin_id');
            $table->boolean('email_enabled')->default(false);
            $table->timestamps();
            $table->unique('admin_id');
            $table->index('email_enabled');
        });
    }

    /**
     * Remove only the notification-preference table created by this feature.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notification_preferences');
    }
};
