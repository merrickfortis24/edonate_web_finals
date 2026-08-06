<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligibility_submissions', function (Blueprint $table) {
            $table->increments('submission_id');
            $table->integer('donor_id');
            $table->dateTime('submitted_at')->useCurrent();
            $table->string('source', 50)->default('mobile_app');
            $table->timestamps();

            $table->foreign('donor_id')
                ->references('donor_id')
                ->on('donors')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_submissions');
    }
};
