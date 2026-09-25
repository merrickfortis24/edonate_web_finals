<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store non-security portal settings as key/value records.
     *
     * This table deliberately stays separate from the protected admin and
     * security tables. It contains only display/contact configuration.
     */
    public function up(): void
    {
        if (Schema::hasTable('system_settings')) {
            return;
        }

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->bigIncrements('system_setting_id');
            $table->string('setting_key', 100)->unique();
            $table->text('setting_value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove only the table created by this migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
