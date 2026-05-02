<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class EligibilityController extends Controller
{
    private const REVIEWED_STATUSES   = ['eligible', 'not_eligible'];
    private const ELIGIBLE_STATUSES   = ['eligible', 'qualified', 'ready'];
    private const INELIGIBLE_STATUSES = ['not_eligible', 'not eligible', 'deferred', 'ineligible'];

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
            'status'     => ['nullable', 'string', Rule::in(['', 'pending', 'eligible', 'not_eligible'])],
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
            ->leftJoin('locations as l', 'l.location_id', '=', 'd.location_id')
            ->leftJoin('admins as adm', 'adm.admin_id',   '=', 'es.reviewed_by_admin_id');

        if ($searchTerm !== '') {
            $like = '%'.$searchTerm.'%';
            $base->where(function ($q) use ($like) {
                $q->whereRaw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) LIKE ?", [$like])
                  ->orWhere('bt.blood_type', 'like', $like)
                  ->orWhereRaw("CONCAT('D', LPAD(d.donor_id, 3, '0')) LIKE ?", [$like]);
            });
        }

        if ($status !== '') {
            if ($status === 'pending') {
                $reviewed = array_merge(self::ELIGIBLE_STATUSES, self::INELIGIBLE_STATUSES);
                $base->whereRaw('LOWER(COALESCE(es.status,\'\')) NOT IN ('.implode(',', array_fill(0, count($reviewed), '?')).')', $reviewed);
            } else {
                $matchList = $status === 'eligible' ? self::ELIGIBLE_STATUSES : self::INELIGIBLE_STATUSES;
                $base->whereRaw('LOWER(COALESCE(es.status,\'\')) IN ('.implode(',', array_fill(0, count($matchList), '?')).')', $matchList);
            }
        }

        if ($bloodType !== '') {
            $base->where('bt.blood_type', $bloodType);
        }

        if ($location !== '') {
            $base->where('l.city', 'like', '%' . $location . '%');
        }

        // Stats on full dataset (no filters)
        $statsRow = DB::table('eligibility_status as es')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(COALESCE(es.status,\'\')) IN (\'eligible\',\'qualified\',\'ready\') THEN 1 ELSE 0 END)                                                  AS eligible,
                SUM(CASE WHEN LOWER(COALESCE(es.status,\'\')) IN (\'not_eligible\',\'not eligible\',\'deferred\',\'ineligible\') THEN 1 ELSE 0 END)                         AS not_eligible,
                SUM(CASE WHEN LOWER(COALESCE(es.status,\'\')) NOT IN (\'eligible\',\'qualified\',\'ready\',\'not_eligible\',\'not eligible\',\'deferred\',\'ineligible\') THEN 1 ELSE 0 END) AS pending
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
                'es.reviewed_at',
                DB::raw("COALESCE(adm.full_name, adm.username) AS reviewed_by_name"),
            ])
            ->orderByRaw("
                CASE
                    WHEN LOWER(COALESCE(es.status,'')) NOT IN
                        ('eligible','qualified','ready','not_eligible','not eligible','deferred','ineligible')
                    THEN 0 ELSE 1
                END
            ")
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
                'total'       => (int) ($statsRow->total       ?? 0),
                'pending'     => (int) ($statsRow->pending     ?? 0),
                'eligible'    => (int) ($statsRow->eligible    ?? 0),
                'not_eligible'=> (int) ($statsRow->not_eligible ?? 0),
            ],
        ]);
    }

    // ── JSON: single record detail ───────────────────────────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $row = DB::table('eligibility_status as es')
            ->join('donors as d',       'd.donor_id',       '=', 'es.donor_id')
            ->join('blood_types as bt',  'bt.blood_type_id', '=', 'd.blood_type_id')
            ->leftJoin('admins as adm',  'adm.admin_id',    '=', 'es.reviewed_by_admin_id')
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
                'es.reviewed_at',
                'es.review_notes',
                DB::raw("COALESCE(adm.full_name, adm.username) AS reviewed_by_name"),
            ])
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        // Latest submission + answers for this donor
        $submission = DB::table('eligibility_submissions')
            ->where('donor_id', $row->donor_id)
            ->orderByDesc('submission_id')
            ->first();

        $answers = [];
        if ($submission) {
            $answers = DB::table('eligibility_answers as ea')
                ->join('eligibility_questions as eq', 'eq.question_id', '=', 'ea.question_id')
                ->where('ea.submission_id', $submission->submission_id)
                ->orderBy('eq.sort_order')
                ->select([
                    'eq.question_text',
                    'eq.question_type',
                    'eq.is_disqualifying',
                    'ea.answer_value',
                ])
                ->get()
                ->map(function (object $a) {
                    $answerLower = Str::lower(trim((string) ($a->answer_value ?? '')));
                    $isFlag = (bool) $a->is_disqualifying
                        && in_array($answerLower, ['yes', '1', 'true'], true);

                    return [
                        'question'      => (string) $a->question_text,
                        'question_type' => (string) $a->question_type,
                        'answer'        => (string) ($a->answer_value ?? '—'),
                        'is_flag'       => $isFlag,
                    ];
                })
                ->all();
        }

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
            'status'       => $this->normalizeStatus((string) ($row->status ?? '')),
            'reviewed_at'  => $row->reviewed_at,
            'review_notes' => $row->review_notes,
            'reviewed_by'  => $row->reviewed_by_name,
            'submitted_at' => $submission?->submitted_at ?? null,
            'answers'      => $answers,
        ]);
    }

    // ── PATCH: admin decision ────────────────────────────────────────────────

    public function review(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(self::REVIEWED_STATUSES)],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $row = DB::table('eligibility_status')->where('eligibility_id', $id)->first();
        if (! $row) {
            return response()->json(['message' => 'Eligibility record not found.'], 404);
        }

        if (in_array($this->normalizeStatus((string) ($row->status ?? '')), self::REVIEWED_STATUSES, true)) {
            return response()->json(['message' => 'This record has already been reviewed.'], 422);
        }

        $actorAdminId = is_numeric($request->session()->get('admin_id'))
            ? (int) $request->session()->get('admin_id')
            : null;

        DB::table('eligibility_status')->where('eligibility_id', $id)->update([
            'status'                => $validated['status'],
            'reviewed_by_admin_id'  => $actorAdminId,
            'reviewed_at'           => now(),
            'review_notes'          => $validated['notes'] ?? null,
        ]);

        $donorId = is_numeric($row->donor_id) ? (int) $row->donor_id : null;
        $code    = 'EL'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
        $label   = $validated['status'] === 'eligible' ? 'Eligible' : 'Not Eligible';

        $this->notifyDonor(
            $donorId,
            'eligibility_reviewed',
            "Your eligibility submission {$code} has been reviewed. Status: {$label}."
        );

        $this->writeAudit($request, 'eligibility_reviewed', "Marked {$code} as {$validated['status']}.", $id, [
            'eligibility_id'  => $id,
            'donor_id'        => $donorId,
            'previous_status' => $row->status ?? null,
            'new_status'      => $validated['status'],
        ]);

        return response()->json([
            'message'        => 'Eligibility status updated.',
            'eligibility_id' => $id,
            'new_status'     => $validated['status'],
            'reviewed_at'    => now()->toISOString(),
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function normalizeStatus(string $value): string
    {
        $lower = Str::lower(trim($value));

        if (in_array($lower, self::ELIGIBLE_STATUSES, true)) {
            return 'eligible';
        }
        if (in_array($lower, self::INELIGIBLE_STATUSES, true)) {
            return 'not_eligible';
        }

        return 'pending';
    }

    private function transformRow(object $row): array
    {
        return [
            'eligibility_id'     => (int) $row->eligibility_id,
            'donor_id'           => (int) $row->donor_id,
            'donor_name'         => trim((string) $row->donor_name),
            'donor_code'         => (string) $row->donor_code,
            'blood_type'         => (string) $row->blood_type,
            'status'             => $this->normalizeStatus((string) ($row->status ?? '')),
            'last_donation_date' => $row->last_donation_date,
            'next_eligible_date' => $row->next_eligible_date,
            'reviewed_at'        => $row->reviewed_at,
            'reviewed_by'        => $row->reviewed_by_name,
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
