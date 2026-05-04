<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillScreeningQuestionDecisions extends Command
{
    protected $signature = 'screening-questions:backfill-decisions {--dry-run : Preview updates without writing}';
    protected $description = 'Backfill risk level decision fields for existing screening questions using question text patterns.';

    private const TABLE = 'screening_questions';

    public function handle(): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            $this->error('Table screening_questions does not exist.');
            return self::FAILURE;
        }

        $requiredColumns = ['question_id', 'question_text', 'risk_level', 'trigger_answer', 'deferral_days', 'recommendation_message'];
        foreach ($requiredColumns as $column) {
            if (! Schema::hasColumn(self::TABLE, $column)) {
                $this->error("Missing required column: {$column}");
                return self::FAILURE;
            }
        }

        $hasUpdatedAt = Schema::hasColumn(self::TABLE, 'updated_at');
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table(self::TABLE)
            ->select($requiredColumns)
            ->orderBy('question_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No screening questions found.');
            return self::SUCCESS;
        }

        $updated = 0;
        $unchanged = 0;
        $preview = 0;

        foreach ($rows as $row) {
            $decision = $this->resolveDecision((string) $row->question_text);

            $normalizedCurrent = $this->normalizeCurrent([
                'risk_level' => $row->risk_level,
                'trigger_answer' => $row->trigger_answer,
                'deferral_days' => $row->deferral_days,
                'recommendation_message' => $row->recommendation_message,
            ]);

            if (! $this->hasDiff($normalizedCurrent, $decision)) {
                $unchanged++;
                continue;
            }

            $payload = $decision;
            if ($hasUpdatedAt) {
                $payload['updated_at'] = now();
            }

            if ($dryRun) {
                $preview++;
                $this->line(sprintf(
                    '[DRY-RUN] #%d %s => risk=%s trigger=%s deferral=%s',
                    (int) $row->question_id,
                    $this->truncate((string) $row->question_text, 70),
                    $payload['risk_level'],
                    $payload['trigger_answer'] ?? '-',
                    $payload['deferral_days'] === null ? '-' : (string) $payload['deferral_days']
                ));
                continue;
            }

            DB::table(self::TABLE)
                ->where('question_id', (int) $row->question_id)
                ->update($payload);

            $updated++;
        }

        $this->info('Backfill completed.');
        $this->line("Total rows: {$rows->count()}");
        if ($dryRun) {
            $this->line("Would update: {$preview}");
        } else {
            $this->line("Updated: {$updated}");
        }
        $this->line("Unchanged: {$unchanged}");

        return self::SUCCESS;
    }

    private function resolveDecision(string $questionText): array
    {
        $normalized = $this->normalizeText($questionText);

        // High-risk auto reject
        if ($this->containsAny($normalized, ['hiv treatment', 'treat hiv', 'hiv infection', 'positive test for hiv', 'positive hiv test'])) {
            return $this->decision('auto_reject', 'yes', null, 'This response is high risk and currently not eligible for blood donation.');
        }

        if ($this->containsAny($normalized, ['hepatitis'])) {
            return $this->decision('auto_reject', 'yes', null, 'History related to hepatitis is currently not eligible for blood donation.');
        }

        if ($this->containsAny($normalized, ['used needles', 'inject drugs', 'steroids', 'not prescribed by your doctor'])) {
            return $this->decision('auto_reject', 'yes', null, 'Use of non-prescribed injected drugs/steroids is high risk and not eligible at this time.');
        }

        if ($this->containsAny($normalized, ['money, drugs, or other payment for sex', 'payment for sex', 'received payment for sex'])) {
            return $this->decision('auto_reject', 'yes', null, 'This response is high risk and currently not eligible for blood donation.');
        }

        if ($this->containsAny($normalized, ['cancer', 'leukemia'])) {
            return $this->decision('auto_reject', 'yes', null, 'Cancer history requires strict safety exclusion from blood donation.');
        }

        // Temporary defer
        if ($this->containsAny($normalized, ['feeling healthy', 'healthy and well today'])) {
            return $this->decision('temporary_defer', 'no', 7, 'Please recover first and donate once you are fully well.');
        }

        if ($this->containsAny($normalized, ['fever', 'cough', 'colds', 'infection'])) {
            return $this->decision('temporary_defer', 'yes', 7, 'Please recover from current symptoms before donating blood.');
        }

        if ($this->containsAny($normalized, ['sleep at least 5 to 6 hours', 'sleep at least'])) {
            return $this->decision('temporary_defer', 'no', 1, 'Please get enough rest before donating blood.');
        }

        if ($this->containsAny($normalized, ['eat a proper meal', 'proper meal before donating'])) {
            return $this->decision('temporary_defer', 'no', 1, 'Please eat a proper meal before donating blood.');
        }

        if ($this->containsAny($normalized, ['consumed alcohol within the last 24 hours', 'alcohol within the last 24 hours'])) {
            return $this->decision('temporary_defer', 'yes', 1, 'Please avoid alcohol for at least 24 hours before donating blood.');
        }

        if ($this->containsAny($normalized, ['aspirin'])) {
            return $this->decision('temporary_defer', 'yes', 2, 'Please wait after aspirin intake before donating blood.');
        }

        if ($this->containsAny($normalized, ['donated blood, platelets, or plasma', 'donated blood', 'double unit of red blood cells', 'apheresis machine'])) {
            return $this->decision('temporary_defer', 'yes', 56, 'Please wait until the required donation interval has passed.');
        }

        if ($this->containsAny($normalized, ['blood transfusion'])) {
            return $this->decision('temporary_defer', 'yes', 365, 'Recent blood transfusion requires temporary deferral.');
        }

        if ($this->containsAny($normalized, ['pregnant', 'ever been pregnant', 'given birth', 'postpartum'])) {
            return $this->decision('temporary_defer', 'yes', 180, 'Pregnancy-related history requires temporary deferral and medical clearance.');
        }

        if ($this->containsAny($normalized, ['tattoo', 'piercing'])) {
            return $this->decision('temporary_defer', 'yes', 365, 'Recent tattoo or piercing requires temporary deferral.');
        }

        if ($this->containsAny($normalized, ['surgery', 'organ transplant', 'graft', 'tissue transplant'])) {
            return $this->decision('for_review', 'yes', null, 'Your answer requires review by authorized personnel.');
        }

        // For review
        if ($this->containsAny($normalized, ['medication', 'antibiotic'])) {
            return $this->decision('for_review', 'yes', null, 'Your answer requires review by authorized personnel.');
        }

        if ($this->containsAny($normalized, ['high or low blood pressure', 'blood pressure'])) {
            return $this->decision('for_review', 'yes', null, 'Your blood pressure status must be reviewed by authorized personnel.');
        }

        if ($this->containsAny($normalized, ['minimum required weight', 'current weight', 'weigh at least'])) {
            return $this->decision('for_review', 'no', null, 'Your weight must be reviewed before donation.');
        }

        if ($this->containsAny($normalized, ['malaria', 'travel'])) {
            return $this->decision('for_review', 'yes', null, 'Travel and malaria-related responses require review by authorized personnel.');
        }

        if ($this->containsAny($normalized, ['clotting factor', 'jail', 'lockup', 'juvenile detention', 'prison'])) {
            return $this->decision('for_review', 'yes', null, 'Your answer requires review by authorized personnel.');
        }

        if ($this->containsAny($normalized, ['heart or lungs', 'heart', 'lungs'])) {
            return $this->decision('for_review', 'yes', null, 'Heart or lung conditions require review by authorized personnel.');
        }

        // Safe fallback
        return $this->decision('safe', null, null, null);
    }

    private function decision(string $riskLevel, ?string $triggerAnswer, ?int $deferralDays, ?string $recommendation): array
    {
        return [
            'risk_level' => $riskLevel,
            'trigger_answer' => $triggerAnswer,
            'deferral_days' => $riskLevel === 'temporary_defer' ? max(1, (int) ($deferralDays ?? 1)) : null,
            'recommendation_message' => $this->nullableString($recommendation),
        ];
    }

    private function normalizeText(string $text): string
    {
        $lower = mb_strtolower(trim($text));
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $lower);
        $normalized = preg_replace('/\s+/', ' ', (string) $normalized);

        return trim((string) $normalized);
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $this->normalizeText($needle))) {
                return true;
            }
        }

        return false;
    }

    private function normalizeCurrent(array $current): array
    {
        $risk = trim((string) ($current['risk_level'] ?? ''));
        if (! in_array($risk, ['safe', 'auto_reject', 'for_review', 'temporary_defer'], true)) {
            $risk = 'safe';
        }

        $trigger = $this->nullableString($current['trigger_answer'] ?? null);
        $deferral = $current['deferral_days'];
        $deferral = $deferral === null ? null : (int) $deferral;
        $recommendation = $this->nullableString($current['recommendation_message'] ?? null);

        if ($risk !== 'temporary_defer') {
            $deferral = null;
        }
        if ($risk === 'safe') {
            $trigger = null;
        }

        return [
            'risk_level' => $risk,
            'trigger_answer' => $trigger,
            'deferral_days' => $deferral,
            'recommendation_message' => $recommendation,
        ];
    }

    private function hasDiff(array $current, array $resolved): bool
    {
        return $current['risk_level'] !== $resolved['risk_level']
            || $this->nullableString($current['trigger_answer']) !== $this->nullableString($resolved['trigger_answer'])
            || (($current['deferral_days'] === null ? null : (int) $current['deferral_days']) !== ($resolved['deferral_days'] === null ? null : (int) $resolved['deferral_days']))
            || $this->nullableString($current['recommendation_message']) !== $this->nullableString($resolved['recommendation_message']);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 3).'...';
    }
}
