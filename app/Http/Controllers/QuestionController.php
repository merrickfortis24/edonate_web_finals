<?php

namespace App\Http\Controllers;

use App\Models\EligibilityQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuestionController extends Controller
{
    // ── Pages ────────────────────────────────────────────────────────────────

    public function index()
    {
        return view('admin.eligibility.questions.index', [
            'questionsPayload' => [
                'api' => [
                    'listUrl'   => route('admin.eligibility.questions.data'),
                    'storeUrl'  => route('admin.eligibility.questions.store'),
                    'updateUrl' => url('/admin/eligibility/questions'),
                    'toggleUrl' => url('/admin/eligibility/questions'),
                ],
            ],
        ]);
    }

    // ── JSON: paginated list ─────────────────────────────────────────────────

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'search'    => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', Rule::in(['', '0', '1', true, false])],
        ]);

        $page      = (int) ($validated['page']     ?? 1);
        $perPage   = (int) ($validated['per_page'] ?? 20);
        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $isActive  = $validated['is_active'] ?? '';

        $query = EligibilityQuestion::query();

        if ($searchTerm !== '') {
            $query->where('question_text', 'like', '%'.$searchTerm.'%');
        }

        if ($isActive !== '' && $isActive !== null) {
            $isActiveBool = filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActiveBool !== null) {
                $query->where('is_active', $isActiveBool);
            }
        }

        $total    = $query->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from     = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
        $to       = min($page * $perPage, $total);

        $rows = $query->orderBy('question_order')
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (EligibilityQuestion $q) => $this->transformQuestion($q))->values()->all(),
            'meta' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $total,
                'from'         => $from,
                'to'           => $to,
            ],
            'stats' => [
                'total'   => $total,
                'active'  => EligibilityQuestion::where('is_active', true)->count(),
                'inactive'=> EligibilityQuestion::where('is_active', false)->count(),
            ],
        ]);
    }

    // ── POST: create new question ────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question_text'    => ['required', 'string', 'min:5', 'max:500'],
            'followup_prompt'  => ['nullable', 'string', 'max:500'],
            'followup_trigger' => ['nullable', Rule::in(['yes', 'no', ''])],
            'question_order'   => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        try {
            $question = EligibilityQuestion::create([
                'question_text'    => trim($validated['question_text']),
                'followup_prompt'  => trim((string) ($validated['followup_prompt'] ?? '')),
                'followup_trigger' => trim((string) ($validated['followup_trigger'] ?? '')),
                'question_order'   => $validated['question_order'],
                'is_active'        => true,
            ]);

            $this->writeAudit($request, 'question_created', "Created question: {$question->question_text}", $question->question_id);

            return response()->json([
                'message'     => 'Question created successfully.',
                'question_id' => $question->question_id,
                'question'    => $this->transformQuestion($question),
            ], 201);
        } catch (Throwable $e) {
            logger()->error('Failed to create question.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create question.'], 500);
        }
    }

    // ── PUT: update question ─────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'question_text'    => ['required', 'string', 'min:5', 'max:500'],
            'followup_prompt'  => ['nullable', 'string', 'max:500'],
            'followup_trigger' => ['nullable', Rule::in(['yes', 'no', ''])],
            'question_order'   => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $question = EligibilityQuestion::find($id);
        if (! $question) {
            return response()->json(['message' => 'Question not found.'], 404);
        }

        try {
            $oldText = $question->question_text;
            $question->update([
                'question_text'    => trim($validated['question_text']),
                'followup_prompt'  => trim((string) ($validated['followup_prompt'] ?? '')),
                'followup_trigger' => trim((string) ($validated['followup_trigger'] ?? '')),
                'question_order'   => $validated['question_order'],
            ]);

            $this->writeAudit($request, 'question_updated', "Updated question from: {$oldText}", $id);

            return response()->json([
                'message'  => 'Question updated successfully.',
                'question' => $this->transformQuestion($question),
            ]);
        } catch (Throwable $e) {
            logger()->error('Failed to update question.', ['error' => $e->getMessage(), 'question_id' => $id]);
            return response()->json(['message' => 'Failed to update question.'], 500);
        }
    }

    // ── PATCH: toggle active/inactive ────────────────────────────────────────

    public function toggle(Request $request, int $id): JsonResponse
    {
        $question = EligibilityQuestion::find($id);
        if (! $question) {
            return response()->json(['message' => 'Question not found.'], 404);
        }

        try {
            $newStatus = ! $question->is_active;
            $question->update(['is_active' => $newStatus]);

            $statusLabel = $newStatus ? 'activated' : 'deactivated';
            $this->writeAudit($request, 'question_toggled', "Question {$statusLabel}: {$question->question_text}", $id);

            return response()->json([
                'message'   => "Question {$statusLabel} successfully.",
                'is_active' => $newStatus,
                'question'  => $this->transformQuestion($question),
            ]);
        } catch (Throwable $e) {
            logger()->error('Failed to toggle question.', ['error' => $e->getMessage(), 'question_id' => $id]);
            return response()->json(['message' => 'Failed to toggle question.'], 500);
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function transformQuestion(EligibilityQuestion $q): array
    {
        return [
            'question_id'      => $q->question_id,
            'question_text'    => $q->question_text,
            'followup_prompt'  => $q->followup_prompt,
            'followup_trigger' => $q->followup_trigger,
            'question_order'   => $q->question_order,
            'is_active'        => $q->is_active,
        ];
    }

    private function writeAudit(
        Request $request,
        string $actionType,
        string $description,
        ?int $questionId = null
    ): void {
        try {
            $actorName = trim((string) (
                $request->session()->get('admin_full_name')
                    ?: $request->session()->get('admin_username')
                    ?: 'Admin'
            ));
            $actorRole = ucfirst((string) $request->session()->get('admin_role', 'admin'));

            if (! Schema::hasTable('audit_logs')) {
                return;
            }

            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id'))
                    ? (int) $request->session()->get('admin_id') : null,
                'actor_name'   => $actorName,
                'actor_role'   => $actorRole,
                'action_type'  => $actionType,
                'module_type'  => 'eligibility',
                'target_table' => 'screening_questions',
                'target_id'    => $questionId,
                'description'  => $description,
                'ip_address'   => $request->ip(),
                'result'       => 'success',
                'metadata'     => null,
                'created_at'   => now(),
            ]);
        } catch (Throwable $e) {
            logger()->warning('Failed to write question audit.', [
                'action_type'   => $actionType,
                'question_id'   => $questionId,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
