<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins') || Schema::hasTable('admin_trusted_devices')) {
            return;
        }

        Schema::create('admin_trusted_devices', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // The legacy admins.admin_id column is a signed INT, not a BIGINT.
            $table->integer('admin_id');
            $table->char('token_hash', 64)->unique();
            $table->string('device_name', 150)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('trusted_at');
            $table->timestamp('last_used_at')->nullable();
            $table->dateTime('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')
                ->references('admin_id')
                ->on('admins')
                ->cascadeOnDelete();
            $table->index(['admin_id', 'expires_at', 'revoked_at'], 'admin_trusted_devices_validity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_trusted_devices');
    }
};
