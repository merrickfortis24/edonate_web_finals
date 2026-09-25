<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_hash', 64)->index();
            $table->string('policy_version', 40);
            $table->string('purpose', 60);
            $table->json('choices');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_receipts');
    }
};
