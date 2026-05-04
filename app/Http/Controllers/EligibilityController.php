<?php

namespace App\Http\Controllers;

use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class EligibilityController extends Controller
{
    private const REVIEWABLE_STATUSES = ['for_review', 'for review', 'pending'];
    private const STATUS_FILTERS = [
        'eligible' => ['eligible', 'approved', 'qualified', 'ready'],
        'not_eligible' => ['not_eligible', 'not eligible', 'declined', 'ineligible'],
        'temporary_deferred' => ['temporary_deferred', 'temporary deferred', 'deferred'],
        'for_review' => ['for_review', 'for review', 'pending'],
    ];

    // ── Pages ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        return view('admin.eligibility.index', [
            'eligibilityPayload' => [
                'api' => [
                    'listUrl'       => route('admin.eligibility.data'),
                    'detailBaseUrl' => url('/admin/eligibility'),
                    'reviewBaseUrl' => url('/admin/eligibility'),
                ],
            ],
        ]);
    }

    // ── JSON: paginated list ─────────────────────────────────────────────────

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page'       => ['nullable', 'integer', 'min:1'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'search'     => ['nullable', 'string', 'max:150'],
            'status'     => ['nullable', 'string', Rule::in(['', 'eligible', 'not_eligible', 'temporary_deferred', 'for_review', 'pending', 'approved', 'declined'])],
            'blood_type' => ['nullable', 'string'],
            'location'   => ['nullable', 'string'],
        ]);

        $page       = (int) ($validated['page']     ?? 1);
        $perPage    = (int) ($validated['per_page'] ?? 10);
        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $status     = Str::lower(trim((string) ($validated['status'] ?? '')));
        $bloodType  = trim((string) ($validated['blood_type'] ?? ''));
        $location   = trim((string) ($validated['location'] ?? ''));

        $base = DB::table('eligibility_status as es')
            ->join('donors as d',      'd.donor_id',      '=', 'es.donor_id')
            ->join('blood_types as bt', 'bt.blood_type_id', '=', 'd.blood_type_id')
            ->leftJoin('locations as l', 'l.location_id', '=', 'd.location_id');

        if ($searchTerm !== '') {
            $like = '%'.$searchTerm.'%';
            $base->where(function ($q) use ($like) {
                $q->whereRaw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) LIKE ?", [$like])
                  ->orWhere('bt.blood_type', 'like', $like)
                  ->orWhereRaw("CONCAT('D', LPAD(d.donor_id, 3, '0')) LIKE ?", [$like]);
            });
        }

        if ($status !== '') {
            $base->whereIn(DB::raw("LOWER(COALESCE(es.status, ''))"), self::STATUS_FILTERS[$status] ?? [$status]);
        }

        if ($bloodType !== '') {
            $base->where('bt.blood_type', $bloodType);
        }

        if ($location !== '') {
            $base->where('l.city', 'like', '%' . $location . '%');
        }

        // Stats on full dataset (no filters)
        $statsRow = DB::table('eligibility_status')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(COALESCE(status, \'\')) IN (\'for_review\', \'for review\', \'pending\') OR status IS NULL THEN 1 ELSE 0 END) AS for_review,
                SUM(CASE WHEN LOWER(COALESCE(status, \'\')) IN (\'eligible\', \'approved\', \'qualified\', \'ready\') THEN 1 ELSE 0 END) AS eligible,
                SUM(CASE WHEN LOWER(COALESCE(status, \'\')) IN (\'temporary_deferred\', \'temporary deferred\', \'deferred\') THEN 1 ELSE 0 END) AS temporary_deferred,
                SUM(CASE WHEN LOWER(COALESCE(status, \'\')) IN (\'not_eligible\', \'not eligible\', \'declined\', \'ineligible\') THEN 1 ELSE 0 END) AS not_eligible
            ')
            ->first();

        $total    = (clone $base)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from     = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
        $to       = min($page * $perPage, $total);

        $selectColumns = [
            'es.eligibility_id',
            'es.donor_id',
            DB::raw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) AS donor_name"),
            DB::raw("CONCAT('D',LPAD(d.donor_id,3,'0')) AS donor_code"),
            'bt.blood_type',
            'es.status',
            'es.last_donation_date',
            'es.next_eligible_date',
            'd.contact_number',
        ];

        foreach (['source', 'result_reason', 'recommendation_message'] as $column) {
            $selectColumns[] = Schema::hasColumn('eligibility_status', $column)
                ? "es.{$column}"
                : DB::raw('NULL as ' . $column);
        }

        $rows = (clone $base)
            ->select($selectColumns)
            ->orderByRaw("CASE WHEN LOWER(COALESCE(es.status, '')) IN ('for_review', 'for review', 'pending') OR es.status IS NULL THEN 0 ELSE 1 END")
            ->orderByDesc('es.eligibility_id')
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (object $row) => $this->transformRow($row))->values()->all(),
            'meta' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $total,
                'from'         => $from,
                'to'           => $to,
            ],
            'stats' => [
                'total'              => (int) ($statsRow->total ?? 0),
                'for_review'         => (int) ($statsRow->for_review ?? 0),
                'eligible'           => (int) ($statsRow->eligible ?? 0),
                'temporary_deferred' => (int) ($statsRow->temporary_deferred ?? 0),
                'not_eligible'       => (int) ($statsRow->not_eligible ?? 0),
            ],
        ]);
    }

    // ── JSON: single record detail ───────────────────────────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $selectColumns = [
            'es.eligibility_id',
            'es.donor_id',
            DB::raw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) AS donor_name"),
            DB::raw("CONCAT('D',LPAD(d.donor_id,3,'0')) AS donor_code"),
            'bt.blood_type',
            'd.contact_number',
            'es.status',
            'es.last_donation_date',
            'es.next_eligible_date',
        ];

        foreach (['source', 'result_reason', 'recommendation_message'] as $column) {
            $selectColumns[] = Schema::hasColumn('eligibility_status', $column)
                ? "es.{$column}"
                : DB::raw('NULL as ' . $column);
        }

        $row = DB::table('eligibility_status as es')
            ->join('donors as d',       'd.donor_id',       '=', 'es.donor_id')
            ->join('blood_types as bt',  'bt.blood_type_id', '=', 'd.blood_type_id')
            ->where('es.eligibility_id', $id)
            ->select($selectColumns)
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        // Screening answers for this eligibility record
        $questionColumns = Schema::hasTable('screening_questions')
            ? Schema::getColumnListing('screening_questions')
            : [];
        $usesDecisionLogic = in_array('risk_level', $questionColumns, true)
            && in_array('trigger_answer', $questionColumns, true);

        $answers = DB::table('donor_screening_answers as dsa')
            ->join('screening_questions as sq', 'sq.question_id', '=', 'dsa.question_id')
            ->where('dsa.eligibility_id', $id)
            ->orderBy('sq.question_order')
            ->select([
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
            ->map(function (object $a) use ($usesDecisionLogic) {
                $riskLevel = Str::lower(trim((string) ($a->risk_level ?? 'safe')));
                $answer = Str::lower(trim((string) ($a->answer ?? '')));
                $triggerAnswer = Str::lower(trim((string) ($a->trigger_answer ?? '')));
                $isFlag = $usesDecisionLogic
                    ? $riskLevel !== 'safe' && $triggerAnswer !== '' && $answer === $triggerAnswer
                    : (($answer === 'yes' && $a->followup_trigger === 'yes')
                        || ($answer === 'no' && $a->followup_trigger === 'no'));

                return [
                    'question'        => (string) $a->question_text,
                    'answer'          => $answer,
                    'followup_answer' => (string) ($a->followup_answer ?? ''),
                    'risk_level'      => $riskLevel,
                    'trigger_answer'  => $triggerAnswer,
                    'deferral_days'   => $a->deferral_days === null ? null : (int) $a->deferral_days,
                    'recommendation_message' => (string) ($a->recommendation_message ?? ''),
                    'is_flag'         => $isFlag,
                ];
            })
            ->all();

        return response()->json([
            'eligibility_id' => (int) $row->eligibility_id,
            'donor' => [
                'donor_id'          => (int) $row->donor_id,
                'donor_code'        => (string) $row->donor_code,
                'name'              => trim((string) $row->donor_name),
                'blood_type'        => (string) $row->blood_type,
                'contact_number'    => (string) ($row->contact_number ?? ''),
                'last_donation_date'=> $row->last_donation_date,
                'next_eligible_date'=> $row->next_eligible_date,
            ],
            'status'  => (string) ($row->status ?? 'for_review'),
            'source'  => (string) ($row->source ?? 'auto'),
            'result_reason' => (string) ($row->result_reason ?? ''),
            'recommendation_message' => (string) ($row->recommendation_message ?? ''),
            'answers' => $answers,
        ]);
    }

    // ── PATCH: admin decision ────────────────────────────────────────────────

    public function review(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['eligible', 'not_eligible', 'approved', 'declined'])],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);
        $newStatus = $this->normalizeReviewedStatus((string) $validated['status']);

        $row = DB::table('eligibility_status')->where('eligibility_id', $id)->first();
        if (! $row) {
            return response()->json(['message' => 'Eligibility record not found.'], 404);
        }

        if (! in_array(Str::lower((string) ($row->status ?? '')), self::REVIEWABLE_STATUSES, true)) {
            return response()->json(['message' => 'Only for-review eligibility records can be manually reviewed.'], 422);
        }

        $update = [
            'status' => $newStatus,
        ];

        if (Schema::hasColumn('eligibility_status', 'source')) {
            $update['source'] = 'admin_review';
        }

        if (Schema::hasColumn('eligibility_status', 'result_reason')) {
            $update['result_reason'] = $newStatus === 'eligible'
                ? 'Approved by authorized personnel after review.'
                : 'Marked not eligible by authorized personnel after review.';
        }

        if (Schema::hasColumn('eligibility_status', 'recommendation_message') && ! empty($validated['notes'])) {
            $update['recommendation_message'] = $validated['notes'];
        }

        if (Schema::hasColumn('eligibility_status', 'reviewed_by_admin_id')) {
            $update['reviewed_by_admin_id'] = is_numeric($request->session()->get('admin_id'))
                ? (int) $request->session()->get('admin_id')
                : null;
        }

        if (Schema::hasColumn('eligibility_status', 'reviewed_at')) {
            $update['reviewed_at'] = now();
        }

        if (Schema::hasColumn('eligibility_status', 'review_notes')) {
            $update['review_notes'] = $validated['notes'] ?? null;
        }

        DB::table('eligibility_status')->where('eligibility_id', $id)->update($update);

        $donorId = is_numeric($row->donor_id) ? (int) $row->donor_id : null;
        $code    = 'EL'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
        $label   = $newStatus === 'eligible' ? 'Eligible' : 'Not Eligible';

        $this->notifyDonor(
            $donorId,
            'eligibility_reviewed',
            "Your eligibility submission {$code} has been reviewed. Status: {$label}."
        );
        app(AdminNotificationService::class)->createAdminEvent(
            'eligibility_reviewed',
            'Eligibility Review Updated',
            "Eligibility submission {$code} was marked as {$newStatus}.",
            'eligibility',
            $id
        );

        $this->writeAudit($request, 'eligibility_reviewed', "Marked {$code} as {$newStatus}.", $id, [
            'eligibility_id'  => $id,
            'donor_id'        => $donorId,
            'previous_status' => $row->status ?? null,
            'new_status'      => $newStatus,
            'source'          => 'admin_review',
            'notes'           => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message'        => 'Eligibility status updated.',
            'eligibility_id' => $id,
            'new_status'     => $newStatus,
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function transformRow(object $row): array
    {
        $status = (string) ($row->status ?? 'for_review');

        return [
            'eligibility_id'     => (int) $row->eligibility_id,
            'donor_id'           => (int) $row->donor_id,
            'donor_name'         => trim((string) $row->donor_name),
            'donor_code'         => (string) $row->donor_code,
            'blood_type'         => (string) $row->blood_type,
            'status'             => $status,
            'last_donation_date' => $row->last_donation_date,
            'next_eligible_date' => $row->next_eligible_date,
            'contact_number'     => (string) ($row->contact_number ?? ''),
            'source'             => (string) ($row->source ?? 'auto'),
            'result_reason'      => (string) ($row->result_reason ?? ''),
            'recommendation_message' => (string) ($row->recommendation_message ?? ''),
            'is_reviewable'      => in_array(Str::lower($status), self::REVIEWABLE_STATUSES, true),
        ];
    }

    private function normalizeReviewedStatus(string $status): string
    {
        return match (Str::lower(trim($status))) {
            'approved', 'eligible' => 'eligible',
            default => 'not_eligible',
        };
    }

    private function notifyDonor(?int $donorId, string $type, string $message): void
    {
        if ($donorId === null || $donorId <= 0 || ! Schema::hasTable('notifications')) {
            return;
        }

        try {
            $payload = [
                'donor_id'          => $donorId,
                'message'           => $message,
                'notification_type' => $type,
                'is_read'           => 0,
                'created_at'        => now(),
            ];

            if (Schema::hasColumn('notifications', 'push_sent')) {
                $payload['push_sent'] = 0;
            }

            DB::table('notifications')->insert($payload);
        } catch (Throwable $e) {
            logger()->warning('Failed to create eligibility notification.', [
                'donor_id' => $donorId,
                'error'    => $e->getMessage(),
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
                'actor_name'   => $actorName,
                'actor_role'   => $actorRole,
                'action_type'  => $actionType,
                'module_type'  => 'eligibility',
                'target_table' => 'eligibility_status',
                'target_id'    => $eligibilityId,
                'description'  => $description,
                'ip_address'   => $request->ip(),
                'result'       => $result,
                'metadata'     => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at'   => now(),
            ]);
        } catch (Throwable $e) {
            logger()->warning('Failed to write eligibility audit.', [
                'action_type'    => $actionType,
                'eligibility_id' => $eligibilityId,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
