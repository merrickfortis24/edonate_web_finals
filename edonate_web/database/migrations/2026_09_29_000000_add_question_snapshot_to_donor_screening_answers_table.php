<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('donor_screening_answers')) {
            return;
        }

        if (! Schema::hasColumn('donor_screening_answers', 'question_snapshot')) {
            Schema::table('donor_screening_answers', function (Blueprint $table): void {
                $column = $table->json('question_snapshot')->nullable();
                if (Schema::hasColumn('donor_screening_answers', 'followup_answer')) {
                    $column->after('followup_answer');
                }
            });
        }

        if (! Schema::hasTable('screening_questions')
            || ! Schema::hasColumn('donor_screening_answers', 'answer_id')
            || ! Schema::hasColumn('donor_screening_answers', 'question_id')
            || ! Schema::hasColumn('screening_questions', 'question_id')
            || ! Schema::hasColumn('screening_questions', 'question_text')) {
            return;
        }

        $selects = [
            'dsa.answer_id as answer_id',
            'dsa.question_id as question_id',
            'sq.question_text as question_text',
        ];
        foreach ([
            'question_order', 'followup_prompt', 'followup_trigger', 'risk_level',
            'trigger_answer', 'deferral_days', 'recommendation_message',
        ] as $column) {
            $selects[] = Schema::hasColumn('screening_questions', $column)
                ? "sq.{$column} as {$column}"
                : DB::raw("NULL as {$column}");
        }

        DB::table('donor_screening_answers as dsa')
            ->join('screening_questions as sq', 'sq.question_id', '=', 'dsa.question_id')
            ->whereNull('dsa.question_snapshot')
            ->select($selects)
            ->orderBy('dsa.answer_id')
            ->chunkById(500, function ($answers): void {
                foreach ($answers as $answer) {
                    $snapshot = [
                        'question_id' => (int) $answer->question_id,
                        'question_text' => (string) $answer->question_text,
                        'question_order' => $answer->question_order === null ? null : (int) $answer->question_order,
                        'followup_prompt' => $answer->followup_prompt,
                        'followup_trigger' => $answer->followup_trigger,
                        'risk_level' => $answer->risk_level,
                        'trigger_answer' => $answer->trigger_answer,
                        'deferral_days' => $answer->deferral_days === null ? null : (int) $answer->deferral_days,
                        'recommendation_message' => $answer->recommendation_message,
                    ];

                    DB::table('donor_screening_answers')
                        ->where('answer_id', $answer->answer_id)
                        ->whereNull('question_snapshot')
                        ->update(['question_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                }
            }, 'dsa.answer_id', 'answer_id');
    }

    public function down(): void
    {
        // Retain captured screening history even if application code is rolled back.
    }
};
