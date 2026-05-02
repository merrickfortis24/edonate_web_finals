<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligibility_answers', function (Blueprint $table) {
            $table->increments('answer_id');
            $table->unsignedInteger('submission_id');
            $table->unsignedInteger('question_id');
            $table->text('answer_value')->nullable();
            $table->timestamps();

            $table->foreign('submission_id')
                ->references('submission_id')
                ->on('eligibility_submissions')
                ->onDelete('cascade');

            $table->foreign('question_id')
                ->references('question_id')
                ->on('eligibility_questions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_answers');
    }
};
