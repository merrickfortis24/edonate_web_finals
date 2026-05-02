<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eligibility_status', function (Blueprint $table) {
            $table->unsignedInteger('reviewed_by_admin_id')->nullable()->after('status');
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by_admin_id');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('eligibility_status', function (Blueprint $table) {
            $table->dropColumn(['reviewed_by_admin_id', 'reviewed_at', 'review_notes']);
        });
    }
};
