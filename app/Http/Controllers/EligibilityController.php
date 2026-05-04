<?php

namespace App\Http\Controllers;

use App\Services\AdminNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class EligibilityController extends Controller
{
    private const STATUS_FILTERS = [
        'eligible' => ['eligible', 'approved', 'qualified', 'ready'],
        'not_eligible' => ['not_eligible', 'not eligible', 'declined', 'ineligible', 'rejected'],
        'temporary_deferred' => ['temporary_deferred', 'temporary deferred', 'temporary_defer', 'temporarily deferred', 'deferred'],
        'for_review' => ['for_review', 'for review', 'pending review', 'pending'],
        'approved' => ['approved', 'eligible'],
        'declined' => ['declined', 'not_eligible', 'not eligible'],
        'pending' => ['pending', 'for_review', 'for review'],
    ];
    private const SOURCE_FILTERS = [
        'auto' => ['auto'],
        'admin_review' => ['admin_review'],
        'legacy' => ['legacy'],
    ];

    public function index(Request $request)
    {
        return view('admin.eligibility.index', [
            'eligibilityPayload' => [
                'api' => [
                    'listUrl' => route('admin.eligibility.data'),
                    'detailBaseUrl' => url('/admin/eligibility'),
                    'reviewBaseUrl' => url('/admin/eligibility'),
                ],
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', Rule::in(['', 'eligible', 'not_eligible', 'temporary_deferred', 'for_review', 'pending', 'approved', 'declined'])],
            'source' => ['nullable', 'string', Rule::in(['', 'auto', 'admin_review', 'legacy'])],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $status = Str::lower(trim((string) ($validated['status'] ?? '')));
        $source = Str::lower(trim((string) ($validated['source'] ?? '')));

        $base = DB::table('eligibility_status as es')
            ->join('donors as d', 'd.donor_id', '=', 'es.donor_id')
            ->leftJoin('blood_types as bt', 'bt.blood_type_id', '=', 'd.blood_type_id');

        $base = $this->joinLatestDonorAuth($base);
        $base = $this->joinReviewerAdmin($base);

        if ($searchTerm !== '') {
            $like = '%' . $searchTerm . '%';
            $base->where(function ($q) use ($like): void {
                $q->whereRaw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) LIKE ?", [$like])
                    ->orWhereRaw("CONCAT('D', LPAD(d.donor_id, 3, '0')) LIKE ?", [$like])
                    ->orWhere('d.contact_number', 'like', $like)
                    ->orWhere('bt.blood_type', 'like', $like)
                    ->orWhere('da.email', 'like', $like);
            });
        }

        if ($status !== '') {
            $base->whereIn(DB::raw("LOWER(TRIM(COALESCE(es.status, '')))"), self::STATUS_FILTERS[$status] ?? [$status]);
        }

        if ($source !== '' && Schema::hasColumn('eligibility_status', 'source')) {
            if ($source === 'legacy') {
                $base->where(function ($q): void {
                    $q->whereNull('es.source')
                        ->orWhereRaw("TRIM(COALESCE(es.source, '')) = ''")
                        ->orWhereIn(DB::raw("LOWER(TRIM(COALESCE(es.source, '')))"), self::SOURCE_FILTERS['legacy']);
                });
            } else {
                $base->whereIn(DB::raw("LOWER(TRIM(COALESCE(es.source, '')))"), self::SOURCE_FILTERS[$source] ?? [$source]);
            }
        }

        $stats = $this->eligibilityStats();

        $total = (clone $base)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
        $to = min($page * $perPage, $total);

        $rows = (clone $base)
            ->select($this->listSelectColumns())
            ->orderByRaw("CASE WHEN LOWER(TRIM(COALESCE(es.status, ''))) IN ('for_review','for review','pending review','pending') OR es.status IS NULL THEN 0 ELSE 1 END")
            ->orderByDesc('es.eligibility_id')
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (object $row): array => $this->transformRow($row))->values()->all(),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
            ],
            'stats' => [
                'total' => $stats['total'],
                'for_review' => $stats['for_review'],
                'eligible' => $stats['eligible'],
                'temporary_deferred' => $stats['temporary_deferred'],
                'not_eligible' => $stats['not_eligible'],
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $query = DB::table('eligibility_status as es')
            ->join('donors as d', 'd.donor_id', '=', 'es.donor_id')
            ->leftJoin('blood_types as bt', 'bt.blood_type_id', '=', 'd.blood_type_id');

        $query = $this->joinLatestDonorAuth($query);
        $query = $this->joinReviewerAdmin($query);

        $row = $query
            ->where('es.eligibility_id', $id)
            ->select($this->detailSelectColumns())
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $questionColumns = Schema::hasTable('screening_questions')
            ? Schema::getColumnListing('screening_questions')
            : [];
        $usesDecisionLogic = in_array('risk_level', $questionColumns, true) && in_array('trigger_answer', $questionColumns, true);

        $answers = DB::table('donor_screening_answers as dsa')
            ->join('screening_questions as sq', 'sq.question_id', '=', 'dsa.question_id')
            ->where('dsa.eligibility_id', $id)
            ->orderBy('sq.question_order')
            ->orderBy('sq.question_id')
            ->select([
                'sq.question_id',
                'sq.question_text',
                'sq.followup_prompt',
                'sq.followup_trigger',
                in_array('risk_level', $questionColumns, true) ? 'sq.risk_level' : DB::raw("'safe' as risk_level"),
                in_array('trigger_answer', $questionColumns, true) ? 'sq.trigger_answer' : DB::raw('NULL as trigger_answer'),
                in_array('deferral_days', $questionColumns, true) ? 'sq.deferral_days' : DB::raw('NULL as deferral_days'),
                in_array('recommendation_message', $questionColumns, true) ? 'sq.recommendation_message' : DB::raw('NULL as recommendation_message'),
                'dsa.answer',
                'dsa.followup_answer',
            ])
            ->get()
            ->map(function (object $a) use ($usesDecisionLogic): array {
                $riskLevel = Str::lower(trim((string) ($a->risk_level ?? 'safe')));
                $answer = Str::lower(trim((string) ($a->answer ?? '')));
                $triggerAnswer = Str::lower(trim((string) ($a->trigger_answer ?? '')));
                $isTriggerMatch = $usesDecisionLogic
                    ? $riskLevel !== 'safe' && $triggerAnswer !== '' && $answer === $triggerAnswer
                    : (($answer === 'yes' && $a->followup_trigger === 'yes') || ($answer === 'no' && $a->followup_trigger === 'no'));

                return [
                    'question_id' => (int) ($a->question_id ?? 0),
                    'question' => (string) $a->question_text,
                    'answer' => $answer,
                    'followup_prompt' => (string) ($a->followup_prompt ?? ''),
                    'followup_answer' => (string) ($a->followup_answer ?? ''),
                    'risk_level' => $riskLevel,
                    'trigger_answer' => $triggerAnswer !== '' ? $triggerAnswer : null,
                    'deferral_days' => $a->deferral_days === null ? null : (int) $a->deferral_days,
                    'recommendation_message' => (string) ($a->recommendation_message ?? ''),
                    'is_trigger_match' => $isTriggerMatch,
                ];
            })
            ->values();

        $triggeredRiskQuestions = $answers
            ->filter(fn (array $answer): bool => $answer['is_trigger_match'] && $answer['risk_level'] !== 'safe')
            ->values()
            ->all();

        return response()->json([
            'eligibility_id' => (int) $row->eligibility_id,
            'raw_status' => $row->status,
            'status' => $this->normalizeStatus((string) ($row->status ?? 'for_review')),
            'status_label' => $this->statusLabel($this->normalizeStatus((string) ($row->status ?? 'for_review'))),
            'source' => $this->normalizeSource($row->source ?? null),
            'source_label' => $this->sourceLabel($this->normalizeSource($row->source ?? null)),
            'result_reason' => $this->fallbackText($row->result_reason ?? null, 'No recorded eligibility reason.'),
            'recommendation_message' => $this->fallbackText($row->recommendation_message ?? null, 'No recommendation recorded.'),
            'next_eligible_date' => $row->next_eligible_date,
            'reviewed_by_admin_id' => $row->reviewed_by_admin_id === null ? null : (int) $row->reviewed_by_admin_id,
            'reviewed_by_name' => (string) ($row->reviewed_by_name ?? ''),
            'reviewed_at' => $row->reviewed_at,
            'review_notes' => (string) ($row->review_notes ?? ''),
            'admin_is_reviewable' => $this->normalizeStatus((string) ($row->status ?? 'for_review')) === 'for_review',
            'donor' => [
                'donor_id' => (int) $row->donor_id,
                'donor_code' => (string) $row->donor_code,
                'name' => trim((string) $row->donor_name),
                'blood_type' => (string) ($row->blood_type ?? ''),
                'email' => (string) ($row->donor_email ?? ''),
                'contact_number' => (string) ($row->contact_number ?? ''),
                'last_donation_date' => $row->last_donation_date,
                'next_eligible_date' => $row->next_eligible_date,
            ],
            'answers' => $answers->all(),
            'triggered_risk_questions' => $triggeredRiskQuestions,
        ]);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['eligible', 'not_eligible', 'temporary_deferred'])],
            'review_notes' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'next_eligible_date' => ['nullable', 'date'],
            'deferral_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $newStatus = Str::lower(trim((string) $validated['status']));
        $reviewNotes = trim((string) ($validated['review_notes'] ?? $validated['notes'] ?? ''));
        $nextEligibleDateInput = trim((string) ($validated['next_eligible_date'] ?? ''));
        $deferralDays = isset($validated['deferral_days']) && is_numeric($validated['deferral_days'])
            ? (int) $validated['deferral_days']
            : null;

        if ($newStatus === 'temporary_deferred' && $nextEligibleDateInput === '' && $deferralDays === null) {
            return response()->json([
                'message' => 'Temporary defer decision requires next eligible date or deferral days.',
                'errors' => [
                    'next_eligible_date' => ['Provide a next eligible date or deferral days.'],
                    'deferral_days' => ['Provide a next eligible date or deferral days.'],
                ],
            ], 422);
        }

        $row = DB::table('eligibility_status')->where('eligibility_id', $id)->first();
        if (! $row) {
            return response()->json(['message' => 'Eligibility record not found.'], 404);
        }

        if (! $this->isReviewableStatus((string) ($row->status ?? ''))) {
            return response()->json(['message' => 'Only for-review eligibility records can be manually reviewed.'], 422);
        }

        $resolvedNextEligibleDate = null;
        if ($newStatus === 'temporary_deferred') {
            if ($nextEligibleDateInput !== '') {
                $resolvedNextEligibleDate = Carbon::parse($nextEligibleDateInput)->toDateString();
            } elseif ($deferralDays !== null) {
                $resolvedNextEligibleDate = now()->addDays($deferralDays)->toDateString();
            }
        }

        $adminId = is_numeric($request->session()->get('admin_id')) ? (int) $request->session()->get('admin_id') : null;
        $update = [
            'status' => $newStatus,
            'next_eligible_date' => $resolvedNextEligibleDate,
        ];

        if (Schema::hasColumn('eligibility_status', 'source')) {
            $update['source'] = 'admin_review';
        }
        if (Schema::hasColumn('eligibility_status', 'result_reason')) {
            $update['result_reason'] = $this->reviewResultReason($newStatus);
        }
        if (Schema::hasColumn('eligibility_status', 'recommendation_message')) {
            $update['recommendation_message'] = $reviewNotes !== '' ? $reviewNotes : $this->reviewRecommendation($newStatus);
        }
        if (Schema::hasColumn('eligibility_status', 'reviewed_by_admin_id')) {
            $update['reviewed_by_admin_id'] = $adminId;
        }
        if (Schema::hasColumn('eligibility_status', 'reviewed_at')) {
            $update['reviewed_at'] = now();
        }
        if (Schema::hasColumn('eligibility_status', 'review_notes')) {
            $update['review_notes'] = $reviewNotes !== '' ? $reviewNotes : null;
        }

        DB::table('eligibility_status')->where('eligibility_id', $id)->update($update);

        $donorId = is_numeric($row->donor_id) ? (int) $row->donor_id : null;
        $code = 'EL' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
        $statusLabel = $this->statusLabel($newStatus);

        $this->notifyDonor(
            $donorId,
            'eligibility_reviewed',
            "Your eligibility submission {$code} has been reviewed. Status: {$statusLabel}."
        );

        app(AdminNotificationService::class)->createAdminEvent(
            'eligibility_reviewed',
            'Eligibility Review Updated',
            "Eligibility submission {$code} was marked as {$newStatus}.",
            'eligibility',
            $id
        );

        $specificActionType = $this->specificReviewActionType($newStatus);
        $metadata = [
            'eligibility_id' => $id,
            'donor_id' => $donorId,
            'previous_status' => $this->normalizeStatus((string) ($row->status ?? '')),
            'new_status' => $newStatus,
            'source' => 'admin_review',
            'review_notes' => $reviewNotes !== '' ? $reviewNotes : null,
            'next_eligible_date' => $resolvedNextEligibleDate,
            'deferral_days' => $deferralDays,
        ];

        $this->writeAudit($request, 'eligibility_reviewed', "Marked {$code} as {$newStatus}.", $id, $metadata);
        $this->writeAudit($request, $specificActionType, "Decision {$specificActionType} for {$code}.", $id, $metadata);

        return response()->json([
            'message' => 'Eligibility status updated.',
            'eligibility_id' => $id,
            'new_status' => $newStatus,
            'source' => 'admin_review',
            'reviewed_by_admin_id' => $adminId,
            'reviewed_at' => now()->toDateTimeString(),
            'next_eligible_date' => $resolvedNextEligibleDate,
        ]);
    }

    private function transformRow(object $row): array
    {
        $rawStatus = $row->status ?? null;
        $status = $this->normalizeStatus((string) ($rawStatus ?? 'for_review'));
        $source = $this->normalizeSource($row->source ?? null);

        return [
            'eligibility_id' => (int) $row->eligibility_id,
            'donor_id' => (int) $row->donor_id,
            'donor_name' => trim((string) $row->donor_name),
            'donor_code' => (string) $row->donor_code,
            'donor_email' => (string) ($row->donor_email ?? ''),
            'contact_number' => (string) ($row->contact_number ?? ''),
            'blood_type' => (string) ($row->blood_type ?? ''),
            'raw_status' => $rawStatus,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'source' => $source,
            'source_label' => $this->sourceLabel($source),
            'result_reason' => $this->fallbackText($row->result_reason ?? null, 'No recorded eligibility reason.'),
            'recommendation_message' => $this->fallbackText($row->recommendation_message ?? null, 'No recommendation recorded.'),
            'last_donation_date' => $row->last_donation_date,
            'next_eligible_date' => $row->next_eligible_date,
            'reviewed_by_admin_id' => $row->reviewed_by_admin_id === null ? null : (int) $row->reviewed_by_admin_id,
            'reviewed_by_name' => (string) ($row->reviewed_by_name ?? ''),
            'reviewed_at' => $row->reviewed_at,
            'review_notes' => (string) ($row->review_notes ?? ''),
            'admin_is_reviewable' => $status === 'for_review',
            'is_reviewable' => $status === 'for_review',
        ];
    }

    private function listSelectColumns(): array
    {
        $hasDonorAuth = Schema::hasTable('donor_authentication');
        $hasReviewer = Schema::hasTable('admins') && Schema::hasColumn('eligibility_status', 'reviewed_by_admin_id');

        $columns = [
            'es.eligibility_id',
            'es.donor_id',
            DB::raw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) AS donor_name"),
            DB::raw("CONCAT('D', LPAD(d.donor_id, 3, '0')) AS donor_code"),
            'd.contact_number',
            'bt.blood_type',
            'es.status',
            'es.last_donation_date',
            'es.next_eligible_date',
            $hasDonorAuth ? DB::raw("COALESCE(da.email, '') as donor_email") : DB::raw("'' as donor_email"),
            Schema::hasColumn('eligibility_status', 'source') ? 'es.source' : DB::raw("'legacy' as source"),
            Schema::hasColumn('eligibility_status', 'result_reason') ? 'es.result_reason' : DB::raw("'' as result_reason"),
            Schema::hasColumn('eligibility_status', 'recommendation_message') ? 'es.recommendation_message' : DB::raw("'' as recommendation_message"),
            Schema::hasColumn('eligibility_status', 'reviewed_by_admin_id') ? 'es.reviewed_by_admin_id' : DB::raw('NULL as reviewed_by_admin_id'),
            Schema::hasColumn('eligibility_status', 'reviewed_at') ? 'es.reviewed_at' : DB::raw('NULL as reviewed_at'),
            Schema::hasColumn('eligibility_status', 'review_notes') ? 'es.review_notes' : DB::raw('NULL as review_notes'),
            $hasReviewer ? DB::raw("COALESCE(reviewer.full_name, reviewer.username, '') as reviewed_by_name") : DB::raw("'' as reviewed_by_name"),
        ];

        return $columns;
    }

    private function detailSelectColumns(): array
    {
        $hasDonorAuth = Schema::hasTable('donor_authentication');
        $hasReviewer = Schema::hasTable('admins') && Schema::hasColumn('eligibility_status', 'reviewed_by_admin_id');

        return [
            'es.eligibility_id',
            'es.donor_id',
            DB::raw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) AS donor_name"),
            DB::raw("CONCAT('D', LPAD(d.donor_id, 3, '0')) AS donor_code"),
            'd.contact_number',
            'bt.blood_type',
            $hasDonorAuth ? DB::raw("COALESCE(da.email, '') as donor_email") : DB::raw("'' as donor_email"),
            'es.status',
            'es.last_donation_date',
            'es.next_eligible_date',
            Schema::hasColumn('eligibility_status', 'source') ? 'es.source' : DB::raw("'legacy' as source"),
            Schema::hasColumn('eligibility_status', 'result_reason') ? 'es.result_reason' : DB::raw("'' as result_reason"),
            Schema::hasColumn('eligibility_status', 'recommendation_message') ? 'es.recommendation_message' : DB::raw("'' as recommendation_message"),
            Schema::hasColumn('eligibility_status', 'reviewed_by_admin_id') ? 'es.reviewed_by_admin_id' : DB::raw('NULL as reviewed_by_admin_id'),
            Schema::hasColumn('eligibility_status', 'reviewed_at') ? 'es.reviewed_at' : DB::raw('NULL as reviewed_at'),
            Schema::hasColumn('eligibility_status', 'review_notes') ? 'es.review_notes' : DB::raw('NULL as review_notes'),
            $hasReviewer ? DB::raw("COALESCE(reviewer.full_name, reviewer.username, '') as reviewed_by_name") : DB::raw("'' as reviewed_by_name"),
        ];
    }

    private function joinLatestDonorAuth($query)
    {
        if (! Schema::hasTable('donor_authentication')) {
            return $query;
        }

        $latestAuth = DB::table('donor_authentication')
            ->select('donor_id', DB::raw('MAX(auth_id) as latest_auth_id'))
            ->groupBy('donor_id');

        return $query
            ->leftJoinSub($latestAuth, 'da_latest', function ($join): void {
                $join->on('da_latest.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('donor_authentication as da', 'da.auth_id', '=', 'da_latest.latest_auth_id');
    }

    private function joinReviewerAdmin($query)
    {
        if (! Schema::hasTable('admins') || ! Schema::hasColumn('eligibility_status', 'reviewed_by_admin_id')) {
            return $query;
        }

        return $query->leftJoin('admins as reviewer', 'reviewer.admin_id', '=', 'es.reviewed_by_admin_id');
    }

    private function normalizeStatus(string $status): string
    {
        $value = Str::lower(trim($status));

        if (in_array($value, ['approved', 'eligible', 'qualified', 'ready'], true)) {
            return 'eligible';
        }
        if (in_array($value, ['declined', 'not_eligible', 'not eligible', 'ineligible', 'rejected'], true)) {
            return 'not_eligible';
        }
        if (in_array($value, ['temporary_deferred', 'temporary deferred', 'temporary_defer', 'temporarily deferred', 'deferred'], true)) {
            return 'temporary_deferred';
        }
        if (in_array($value, ['for_review', 'for review', 'pending review', 'pending'], true)) {
            return 'for_review';
        }

        return $value !== '' ? $value : 'for_review';
    }

    private function normalizeSource(?string $source): string
    {
        $value = Str::lower(trim((string) $source));
        return in_array($value, ['auto', 'admin_review'], true) ? $value : 'legacy';
    }

    private function isReviewableStatus(string $status): bool
    {
        return $this->normalizeStatus($status) === 'for_review';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'eligible' => 'Eligible',
            'not_eligible' => 'Not Eligible',
            'temporary_deferred' => 'Temporary Deferred',
            default => 'For Review',
        };
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'auto' => 'Auto',
            'admin_review' => 'Admin Review',
            default => 'Legacy',
        };
    }

    private function fallbackText(?string $value, string $fallback): string
    {
        $text = trim((string) $value);
        return $text !== '' ? $text : $fallback;
    }

    private function eligibilityStats(): array
    {
        $stats = [
            'total' => 0,
            'for_review' => 0,
            'eligible' => 0,
            'temporary_deferred' => 0,
            'not_eligible' => 0,
        ];

        DB::table('eligibility_status')
            ->pluck('status')
            ->each(function ($rawStatus) use (&$stats): void {
                $stats['total']++;
                $status = $this->normalizeStatus((string) ($rawStatus ?? 'for_review'));
                if (array_key_exists($status, $stats)) {
                    $stats[$status]++;
                }
            });

        return $stats;
    }

    private function specificReviewActionType(string $status): string
    {
        return match ($status) {
            'eligible' => 'eligibility_approved',
            'temporary_deferred' => 'eligibility_deferred',
            default => 'eligibility_rejected',
        };
    }

    private function reviewResultReason(string $status): string
    {
        return match ($status) {
            'eligible' => 'Approved as eligible by authorized personnel after review.',
            'temporary_deferred' => 'Marked as temporarily deferred by authorized personnel after review.',
            default => 'Rejected as not eligible by authorized personnel after review.',
        };
    }

    private function reviewRecommendation(string $status): string
    {
        return match ($status) {
            'eligible' => 'You may proceed to the next donation step as advised by authorized personnel.',
            'temporary_deferred' => 'Please follow the defer period and return on the next eligible date.',
            default => 'Please follow the guidance provided by authorized personnel before attempting donation again.',
        };
    }

    private function notifyDonor(?int $donorId, string $type, string $message): void
    {
        if ($donorId === null || $donorId <= 0 || ! Schema::hasTable('notifications')) {
            return;
        }

        try {
            $payload = [
                'donor_id' => $donorId,
                'message' => $message,
                'notification_type' => $type,
                'is_read' => 0,
                'created_at' => now(),
            ];

            if (Schema::hasColumn('notifications', 'push_sent')) {
                $payload['push_sent'] = 0;
            }

            DB::table('notifications')->insert($payload);
        } catch (Throwable $e) {
            logger()->warning('Failed to create eligibility notification.', [
                'donor_id' => $donorId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function writeAudit(
        Request $request,
        string $actionType,
        string $description,
        ?int $eligibilityId = null,
        array $metadata = [],
        string $result = 'success'
    ): void {
        try {
            $actorName = trim((string) (
                $request->session()->get('admin_full_name')
                ?: $request->session()->get('admin_username')
                ?: 'Admin'
            ));
            $actorRole = ucfirst((string) $request->session()->get('admin_role', 'admin'));

            if (! Schema::hasTable('audit_logs')) {
                logger()->info('Eligibility audit', compact('actionType', 'description', 'eligibilityId', 'metadata'));
                return;
            }

            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id'))
                    ? (int) $request->session()->get('admin_id') : null,
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'action_type' => $actionType,
                'module_type' => 'eligibility',
                'target_table' => 'eligibility_status',
                'target_id' => $eligibilityId,
                'description' => $description,
                'ip_address' => $request->ip(),
                'result' => $result,
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            logger()->warning('Failed to write eligibility audit.', [
                'action_type' => $actionType,
                'eligibility_id' => $eligibilityId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
