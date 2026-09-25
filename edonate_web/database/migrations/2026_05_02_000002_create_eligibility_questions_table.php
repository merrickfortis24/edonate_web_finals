<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligibility_questions', function (Blueprint $table) {
            $table->increments('question_id');
            $table->string('question_text', 500);
            $table->enum('question_type', ['yes_no', 'text', 'date'])->default('yes_no');
            $table->boolean('is_disqualifying')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_questions');
    }
};
