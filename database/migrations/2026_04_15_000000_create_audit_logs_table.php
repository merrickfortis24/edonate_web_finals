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
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('audit_log_id');
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_role', 50)->nullable();
            $table->string('action_type', 50);
            $table->string('module_type', 80)->nullable();
            $table->string('target_table', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('description', 255);
            $table->string('ip_address', 45)->nullable();
            $table->string('result', 30)->default('success');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action_type', 'created_at']);
            $table->index(['target_table', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
