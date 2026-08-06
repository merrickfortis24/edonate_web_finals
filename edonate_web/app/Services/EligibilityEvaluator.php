<?php

namespace App\Services;

use App\Models\EligibilityQuestion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EligibilityEvaluator
{
    private const DEFAULT_ELIGIBLE_MESSAGE = 'You are initially eligible to donate blood. Please proceed to the next step.';

    /**
     * @param array<int|string, mixed> $answers
     * @return array{
     *     status: string,
     *     result_reason: string,
     *     recommendation_message: string,
     *     next_eligible_date: string|null,
     *     source: string,
     *     matched_questions: array<int, array<string, mixed>>
     * }
     */
    public function evaluate(array $answers): array
    {
        $normalizedAnswers = $this->normalizeAnswerMap($answers);
        $questions = $this->activeQuestions();
        $matches = [];

        foreach ($questions as $question) {
            $questionId = (int) $question->question_id;
            $answer = $normalizedAnswers[$questionId] ?? null;
            $riskLevel = $this->normalizeRiskLevel((string) ($question->risk_level ?? 'safe'));
            $triggerAnswer = $this->normalizeAnswer($question->trigger_answer);

            if ($answer === null || $triggerAnswer === null || $riskLevel === 'safe') {
                continue;
            }

            if ($answer !== $triggerAnswer) {
                continue;
            }

            $matches[] = [
                'question_id' => $questionId,
                'question_text' => (string) $question->question_text,
                'risk_level' => $riskLevel,
                'answer' => $answer,
                'trigger_answer' => $triggerAnswer,
                'deferral_days' => $question->deferral_days === null ? null : max(1, (int) $question->deferral_days),
                'recommendation_message' => trim((string) ($question->recommendation_message ?? '')),
            ];
        }

        return $this->decide($matches);
    }

    /**
     * @return Collection<int, EligibilityQuestion>
     */
    private function activeQuestions(): Collection
    {
        return EligibilityQuestion::query()
            ->where('is_active', true)
            ->orderBy('question_order')
            ->orderBy('question_id')
            ->get();
    }

    /**
     * @param array<int|string, mixed> $answers
     * @return array<int, string>
     */
    private function normalizeAnswerMap(array $answers): array
    {
        $normalized = [];

        foreach ($answers as $questionId => $answer) {
            if (! is_numeric($questionId)) {
                continue;
            }

            $value = $this->normalizeAnswer($answer);
            if ($value === null) {
                continue;
            }

            $normalized[(int) $questionId] = $value;
        }

        return $normalized;
    }

    private function normalizeAnswer(mixed $answer): ?string
    {
        if ($answer === null || $answer === '') {
            return null;
        }

        if (is_bool($answer)) {
            return $answer ? 'yes' : 'no';
        }

        if (is_int($answer) || is_float($answer)) {
            return (int) $answer === 1 ? 'yes' : ((int) $answer === 0 ? 'no' : null);
        }

        $value = strtolower(trim((string) $answer));

        return match ($value) {
            'yes', 'y', 'true', '1', 'on', 'checked' => 'yes',
            'no', 'n', 'false', '0', 'off', 'unchecked' => 'no',
            default => null,
        };
    }

    private function normalizeRiskLevel(string $riskLevel): string
    {
        $value = strtolower(trim($riskLevel));

        return in_array($value, ['safe', 'auto_reject', 'for_review', 'temporary_defer'], true)
            ? $value
            : 'safe';
    }

    /**
     * @param array<int, array<string, mixed>> $matches
     * @return array{
     *     status: string,
     *     result_reason: string,
     *     recommendation_message: string,
     *     next_eligible_date: string|null,
     *     source: string,
     *     matched_questions: array<int, array<string, mixed>>
     * }
     */
    private function decide(array $matches): array
    {
        $autoReject = $this->matchesForRisk($matches, 'auto_reject');
        if ($autoReject !== []) {
            $primary = $autoReject[0];

            return $this->result(
                'not_eligible',
                $this->buildReason('Automatic not eligible decision', $autoReject),
                $this->recommendationFrom($primary, 'Your answer indicates that you are not eligible to donate blood at this time.'),
                null,
                $autoReject
            );
        }

        $temporaryDefers = $this->matchesForRisk($matches, 'temporary_defer');
        if ($temporaryDefers !== []) {
            usort($temporaryDefers, fn (array $left, array $right): int => ((int) ($right['deferral_days'] ?? 1)) <=> ((int) ($left['deferral_days'] ?? 1)));
            $primary = $temporaryDefers[0];
            $deferralDays = max(1, (int) ($primary['deferral_days'] ?? 1));

            return $this->result(
                'temporary_deferred',
                $this->buildReason("Temporary deferral for {$deferralDays} day(s)", $temporaryDefers),
                $this->recommendationFrom($primary, 'Please wait until the next eligible date before donating blood.'),
                Carbon::today()->addDays($deferralDays)->toDateString(),
                $temporaryDefers
            );
        }

        $forReview = $this->matchesForRisk($matches, 'for_review');
        if ($forReview !== []) {
            $primary = $forReview[0];

            return $this->result(
                'for_review',
                $this->buildReason('Submission requires admin review', $forReview),
                $this->recommendationFrom($primary, 'Your answer requires review by authorized personnel.'),
                null,
                $forReview
            );
        }

        return $this->result(
            'eligible',
            'Passed initial eligibility screening',
            self::DEFAULT_ELIGIBLE_MESSAGE,
            null,
            []
        );
    }

    /**
     * @param array<int, array<string, mixed>> $matches
     * @return array<int, array<string, mixed>>
     */
    private function matchesForRisk(array $matches, string $riskLevel): array
    {
        return array_values(array_filter(
            $matches,
            fn (array $match): bool => ($match['risk_level'] ?? null) === $riskLevel
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $matches
     */
    private function buildReason(string $prefix, array $matches): string
    {
        $questions = array_map(
            fn (array $match): string => (string) ($match['question_text'] ?? ''),
            $matches
        );

        $questions = array_values(array_filter($questions, fn (string $question): bool => trim($question) !== ''));

        if ($questions === []) {
            return $prefix;
        }

        return $prefix . ': ' . implode('; ', $questions);
    }

    /**
     * @param array<string, mixed> $match
     */
    private function recommendationFrom(array $match, string $fallback): string
    {
        $message = trim((string) ($match['recommendation_message'] ?? ''));

        return $message !== '' ? $message : $fallback;
    }

    /**
     * @param array<int, array<string, mixed>> $matches
     * @return array{
     *     status: string,
     *     result_reason: string,
     *     recommendation_message: string,
     *     next_eligible_date: string|null,
     *     source: string,
     *     matched_questions: array<int, array<string, mixed>>
     * }
     */
    private function result(
        string $status,
        string $resultReason,
        string $recommendationMessage,
        ?string $nextEligibleDate,
        array $matches
    ): array {
        return [
            'status' => $status,
            'result_reason' => $resultReason,
            'recommendation_message' => $recommendationMessage,
            'next_eligible_date' => $nextEligibleDate,
            'source' => 'auto',
            'matched_questions' => $matches,
        ];
    }
}
