<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'screening_questions';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->addColumnIfMissing('risk_level', function (Blueprint $table): void {
            $column = $table->enum('risk_level', ['safe', 'auto_reject', 'for_review', 'temporary_defer'])
                ->default('safe');

            $this->placeAfterIfPossible($column, 'extra_data');
        });

        $this->addColumnIfMissing('trigger_answer', function (Blueprint $table): void {
            $column = $table->enum('trigger_answer', ['yes', 'no'])->nullable();

            $this->placeAfterIfPossible($column, 'risk_level');
        });

        $this->addColumnIfMissing('deferral_days', function (Blueprint $table): void {
            $column = $table->integer('deferral_days')->nullable();

            $this->placeAfterIfPossible($column, 'trigger_answer');
        });

        $this->addColumnIfMissing('recommendation_message', function (Blueprint $table): void {
            $column = $table->text('recommendation_message')->nullable();

            $this->placeAfterIfPossible($column, 'deferral_days');
        });

        $this->seedPracticalQuestions();
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = collect([
            'recommendation_message',
            'deferral_days',
            'trigger_answer',
            'risk_level',
        ])->filter(fn (string $column): bool => Schema::hasColumn(self::TABLE, $column))->values()->all();

        if ($columns === []) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    private function addColumnIfMissing(string $column, Closure $callback): void
    {
        if (Schema::hasColumn(self::TABLE, $column)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($callback): void {
            $callback($table);
        });
    }

    private function placeAfterIfPossible(mixed $column, string $after): void
    {
        if (Schema::hasColumn(self::TABLE, $after)) {
            $column->after($after);
        }
    }

    private function seedPracticalQuestions(): void
    {
        $questions = [
            [
                'question_text' => 'Did you sleep at least 5 to 6 hours last night?',
                'followup_prompt' => 'How many hours did you sleep?',
                'followup_trigger' => 'no',
                'risk_level' => 'temporary_defer',
                'trigger_answer' => 'no',
                'deferral_days' => 1,
                'recommendation_message' => 'Please get enough rest before donating blood.',
            ],
            [
                'question_text' => 'Did you eat a proper meal before donating blood?',
                'followup_prompt' => 'When was your last meal?',
                'followup_trigger' => 'no',
                'risk_level' => 'temporary_defer',
                'trigger_answer' => 'no',
                'deferral_days' => 1,
                'recommendation_message' => 'Please eat a proper meal before donating blood.',
            ],
            [
                'question_text' => 'Have you consumed alcohol within the last 24 hours?',
                'followup_prompt' => 'When did you last consume alcohol?',
                'followup_trigger' => 'yes',
                'risk_level' => 'temporary_defer',
                'trigger_answer' => 'yes',
                'deferral_days' => 1,
                'recommendation_message' => 'Please avoid alcohol before donating blood.',
            ],
            [
                'question_text' => 'Do you currently have fever, cough, colds, or any infection?',
                'followup_prompt' => 'Please describe your current symptoms.',
                'followup_trigger' => 'yes',
                'risk_level' => 'temporary_defer',
                'trigger_answer' => 'yes',
                'deferral_days' => 7,
                'recommendation_message' => 'Please recover first before donating blood.',
            ],
            [
                'question_text' => 'Have you had a tattoo, piercing, or surgery recently?',
                'followup_prompt' => 'Please indicate when it happened.',
                'followup_trigger' => 'yes',
                'risk_level' => 'for_review',
                'trigger_answer' => 'yes',
                'deferral_days' => null,
                'recommendation_message' => 'Your answer requires review by authorized personnel.',
            ],
            [
                'question_text' => 'Do you weigh at least the minimum required weight for blood donation?',
                'followup_prompt' => 'Please indicate your current weight.',
                'followup_trigger' => 'no',
                'risk_level' => 'for_review',
                'trigger_answer' => 'no',
                'deferral_days' => null,
                'recommendation_message' => 'Your weight must be reviewed before donation.',
            ],
            [
                'question_text' => 'Do you currently have high or low blood pressure?',
                'followup_prompt' => 'Please indicate your latest blood pressure if known.',
                'followup_trigger' => 'yes',
                'risk_level' => 'for_review',
                'trigger_answer' => 'yes',
                'deferral_days' => null,
                'recommendation_message' => 'Your blood pressure must be checked before donation.',
            ],
        ];

        $columns = Schema::getColumnListing(self::TABLE);
        $maxOrder = in_array('question_order', $columns, true)
            ? (int) DB::table(self::TABLE)->max('question_order')
            : 0;

        foreach ($questions as $question) {
            $existing = DB::table(self::TABLE)
                ->where('question_text', $question['question_text'])
                ->first();

            $payload = $this->filterPayload($question, $columns);

            if ($existing) {
                if (in_array('updated_at', $columns, true)) {
                    $payload['updated_at'] = now();
                }

                DB::table(self::TABLE)
                    ->where('question_id', $existing->question_id)
                    ->update($payload);

                continue;
            }

            if (in_array('question_order', $columns, true)) {
                $payload['question_order'] = ++$maxOrder;
            }

            if (in_array('is_active', $columns, true)) {
                $payload['is_active'] = true;
            }

            if (in_array('created_at', $columns, true)) {
                $payload['created_at'] = now();
            }

            if (in_array('updated_at', $columns, true)) {
                $payload['updated_at'] = now();
            }

            DB::table(self::TABLE)->insert($payload);
        }
    }

    private function filterPayload(array $payload, array $columns): array
    {
        return collect($payload)
            ->filter(fn (mixed $value, string $column): bool => in_array($column, $columns, true))
            ->all();
    }
};
