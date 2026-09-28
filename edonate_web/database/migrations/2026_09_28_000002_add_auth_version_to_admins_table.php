<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins') || Schema::hasColumn('admins', 'auth_version')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table): void {
            $table->unsignedInteger('auth_version')->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'auth_version')) {
            Schema::table('admins', function (Blueprint $table): void {
                $table->dropColumn('auth_version');
            });
        }
    }
};
