<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('donors') || Schema::hasColumn('donors', 'is_active')) {
            return;
        }

        Schema::table('donors', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->index('is_active', 'idx_donors_is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('donors') || ! Schema::hasColumn('donors', 'is_active')) {
            return;
        }

        Schema::table('donors', function (Blueprint $table): void {
            $table->dropIndex('idx_donors_is_active');
            $table->dropColumn('is_active');
        });
    }
};
