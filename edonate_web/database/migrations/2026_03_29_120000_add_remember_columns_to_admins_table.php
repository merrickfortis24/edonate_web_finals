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
        if (!Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            if (!Schema::hasColumn('admins', 'remember_token')) {
                $table->string('remember_token', 100)->nullable();
            }

            if (!Schema::hasColumn('admins', 'remember_token_expires_at')) {
                $table->timestamp('remember_token_expires_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('admins', 'remember_token')) {
                $dropColumns[] = 'remember_token';
            }

            if (Schema::hasColumn('admins', 'remember_token_expires_at')) {
                $dropColumns[] = 'remember_token_expires_at';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
