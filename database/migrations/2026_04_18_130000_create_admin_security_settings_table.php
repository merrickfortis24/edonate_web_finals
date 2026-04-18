<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_security_settings')) {
            return;
        }

        Schema::create('admin_security_settings', function (Blueprint $table): void {
            $table->bigIncrements('admin_security_setting_id');
            $table->boolean('enforce_two_factor')->default(true);
            $table->unsignedTinyInteger('session_timeout_minutes')->default(10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('admin_security_settings')) {
            return;
        }

        Schema::drop('admin_security_settings');
    }
};
