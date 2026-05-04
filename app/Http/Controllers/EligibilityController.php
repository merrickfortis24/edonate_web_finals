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
    private const VALID_STATUSES = ['pending', 'approved', 'declined'];

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
            'status'     => ['nullable', 'string', Rule::in(['', 'pending', 'approved', 'declined'])],
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
            $base->where('es.status', $status);
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
                SUM(CASE WHEN status = \'approved\' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status = \'declined\' THEN 1 ELSE 0 END) AS declined,
                SUM(CASE WHEN status = \'pending\' OR status IS NULL THEN 1 ELSE 0 END) AS pending
            ')
            ->first();

        $total    = (clone $base)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from     = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
        $to       = min($page * $perPage, $total);

        $rows = (clone $base)
            ->select([
                'es.eligibility_id',
                'es.donor_id',
                DB::raw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) AS donor_name"),
                DB::raw("CONCAT('D',LPAD(d.donor_id,3,'0')) AS donor_code"),
                'bt.blood_type',
                'es.status',
                'es.last_donation_date',
                'es.next_eligible_date',
                'd.contact_number',
            ])
            ->orderByRaw("CASE WHEN es.status = 'pending' OR es.status IS NULL THEN 0 ELSE 1 END")
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
                'total'    => (int) ($statsRow->total    ?? 0),
                'pending'  => (int) ($statsRow->pending  ?? 0),
                'approved' => (int) ($statsRow->approved ?? 0),
                'declined' => (int) ($statsRow->declined ?? 0),
            ],
        ]);
    }

    // ── JSON: single record detail ───────────────────────────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $row = DB::table('eligibility_status as es')
            ->join('donors as d',       'd.donor_id',       '=', 'es.donor_id')
            ->join('blood_types as bt',  'bt.blood_type_id', '=', 'd.blood_type_id')
            ->where('es.eligibility_id', $id)
            ->select([
                'es.eligibility_id',
                'es.donor_id',
                DB::raw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) AS donor_name"),
                DB::raw("CONCAT('D',LPAD(d.donor_id,3,'0')) AS donor_code"),
                'bt.blood_type',
                'd.contact_number',
                'es.status',
                'es.last_donation_date',
                'es.next_eligible_date',
            ])
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
                $riskLevel = (string) ($a->risk_level ?? 'safe');
                $triggerAnswer = (string) ($a->trigger_answer ?? '');
                $isFlag = $usesDecisionLogic
                    ? $riskLevel !== 'safe' && $triggerAnswer !== '' && (string) $a->answer === $triggerAnswer
                    : (($a->answer === 'yes' && $a->followup_trigger === 'yes')
                        || ($a->answer === 'no' && $a->followup_trigger === 'no'));

                return [
                    'question'        => (string) $a->question_text,
                    'answer'          => (string) $a->answer,
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
            'status'  => (string) ($row->status ?? 'pending'),
            'answers' => $answers,
        ]);
    }

    // ── PATCH: admin decision ────────────────────────────────────────────────

    public function review(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['approved', 'declined'])],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $row = DB::table('eligibility_status')->where('eligibility_id', $id)->first();
        if (! $row) {
            return response()->json(['message' => 'Eligibility record not found.'], 404);
        }

        if (in_array($row->status, ['approved', 'declined'], true)) {
            return response()->json(['message' => 'This record has already been reviewed.'], 422);
        }

        DB::table('eligibility_status')->where('eligibility_id', $id)->update([
            'status' => $validated['status'],
        ]);

        $donorId = is_numeric($row->donor_id) ? (int) $row->donor_id : null;
        $code    = 'EL'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
        $label   = $validated['status'] === 'approved' ? 'Approved' : 'Declined';

        $this->notifyDonor(
            $donorId,
            'eligibility_reviewed',
            "Your eligibility submission {$code} has been reviewed. Status: {$label}."
        );
        app(AdminNotificationService::class)->createAdminEvent(
            'eligibility_reviewed',
            'Eligibility Review Updated',
            "Eligibility submission {$code} was marked as {$validated['status']}.",
            'eligibility',
            $id
        );

        $this->writeAudit($request, 'eligibility_reviewed', "Marked {$code} as {$validated['status']}.", $id, [
            'eligibility_id'  => $id,
            'donor_id'        => $donorId,
            'previous_status' => $row->status ?? null,
            'new_status'      => $validated['status'],
            'notes'           => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message'        => 'Eligibility status updated.',
            'eligibility_id' => $id,
            'new_status'     => $validated['status'],
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function transformRow(object $row): array
    {
        return [
            'eligibility_id'     => (int) $row->eligibility_id,
            'donor_id'           => (int) $row->donor_id,
            'donor_name'         => trim((string) $row->donor_name),
            'donor_code'         => (string) $row->donor_code,
            'blood_type'         => (string) $row->blood_type,
            'status'             => (string) ($row->status ?? 'pending'),
            'last_donation_date' => $row->last_donation_date,
            'next_eligible_date' => $row->next_eligible_date,
            'contact_number'     => (string) ($row->contact_number ?? ''),
        ];
    }

    private function notifyDonor(?int $donorId, string $type, string $message): void
    {
        if ($donorId === null || $donorId <= 0 || ! Schema::hasTable('notifications')) {
            return;
        }

        try {
            DB::table('notifications')->insert([
                'donor_id'          => $donorId,
                'message'           => $message,
                'notification_type' => $type,
                'is_read'           => 0,
                'created_at'        => now(),
                'push_sent'         => 0,
            ]);
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
