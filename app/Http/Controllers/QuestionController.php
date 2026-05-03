<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class QuestionController extends Controller
{
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

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'search'    => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', Rule::in(['', '0', '1', true, false])],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $isActive = $validated['is_active'] ?? '';

        $table = $this->questionTable();
        if ($table === null) {
            return response()->json($this->emptyQuestionPayload($page, $perPage));
        }

        $columns = $this->questionColumns($table);
        $orderColumn = $this->questionOrderColumn($columns);
        $query = DB::table($table);

        if ($searchTerm !== '') {
            $query->where('question_text', 'like', '%'.$searchTerm.'%');
        }

        if (in_array('is_active', $columns, true) && $isActive !== '' && $isActive !== null) {
            $isActiveBool = filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActiveBool !== null) {
                $query->where('is_active', $isActiveBool);
            }
        }

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
        $to = min($page * $perPage, $total);

        $rows = (clone $query)
            ->select($this->questionSelects($columns))
            ->orderBy($orderColumn)
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (object $q) => $this->transformQuestion($q))->values()->all(),
            'meta' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $total,
                'from'         => $from,
                'to'           => $to,
            ],
            'stats' => [
                'total'    => $total,
                'active'   => in_array('is_active', $columns, true) ? DB::table($table)->where('is_active', true)->count() : $total,
                'inactive' => in_array('is_active', $columns, true) ? DB::table($table)->where('is_active', false)->count() : 0,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question_text'    => ['required', 'string', 'min:5', 'max:500'],
            'followup_prompt'  => ['nullable', 'string', 'max:500'],
            'followup_trigger' => ['nullable', Rule::in(['yes', 'no', ''])],
            'question_order'   => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        try {
            $table = $this->questionTable();
            if ($table === null) {
                return response()->json(['message' => 'Question table is not available. Run the eligibility question migration first.'], 409);
            }

            $columns = $this->questionColumns($table);
            $payload = $this->questionWritePayload($validated, $columns, true);
            $questionId = DB::table($table)->insertGetId($payload);
            $question = DB::table($table)
                ->select($this->questionSelects($columns))
                ->where('question_id', $questionId)
                ->first();

            $this->writeAudit($request, 'question_created', "Created question: {$question->question_text}", $questionId);

            return response()->json([
                'message'     => 'Question created successfully.',
                'question_id' => $questionId,
                'question'    => $this->transformQuestion($question),
            ], 201);
        } catch (Throwable $e) {
            logger()->error('Failed to create question.', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to create question.'], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'question_text'    => ['required', 'string', 'min:5', 'max:500'],
            'followup_prompt'  => ['nullable', 'string', 'max:500'],
            'followup_trigger' => ['nullable', Rule::in(['yes', 'no', ''])],
            'question_order'   => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $table = $this->questionTable();
        if ($table === null) {
            return response()->json(['message' => 'Question table is not available. Run the eligibility question migration first.'], 409);
        }

        $columns = $this->questionColumns($table);
        $question = DB::table($table)->where('question_id', $id)->first();
        if (! $question) {
            return response()->json(['message' => 'Question not found.'], 404);
        }

        try {
            $oldText = (string) $question->question_text;

            DB::table($table)
                ->where('question_id', $id)
                ->update($this->questionWritePayload($validated, $columns, false));

            $question = DB::table($table)
                ->select($this->questionSelects($columns))
                ->where('question_id', $id)
                ->first();

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

    public function toggle(Request $request, int $id): JsonResponse
    {
        $table = $this->questionTable();
        if ($table === null) {
            return response()->json(['message' => 'Question table is not available. Run the eligibility question migration first.'], 409);
        }

        $columns = $this->questionColumns($table);
        if (! in_array('is_active', $columns, true)) {
            return response()->json(['message' => 'This question table does not support active/inactive status.'], 422);
        }

        $question = DB::table($table)->where('question_id', $id)->first();
        if (! $question) {
            return response()->json(['message' => 'Question not found.'], 404);
        }

        try {
            $newStatus = ! (bool) $question->is_active;
            $payload = ['is_active' => $newStatus];
            if (in_array('updated_at', $columns, true)) {
                $payload['updated_at'] = now();
            }

            DB::table($table)->where('question_id', $id)->update($payload);
            $question = DB::table($table)
                ->select($this->questionSelects($columns))
                ->where('question_id', $id)
                ->first();

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

    private function transformQuestion(object $q): array
    {
        return [
            'question_id'      => (int) $q->question_id,
            'question_text'    => (string) $q->question_text,
            'followup_prompt'  => (string) ($q->followup_prompt ?? ''),
            'followup_trigger' => (string) ($q->followup_trigger ?? ''),
            'question_order'   => (int) ($q->question_order ?? 0),
            'is_active'        => (bool) ($q->is_active ?? true),
        ];
    }

    private function questionTable(): ?string
    {
        foreach (['screening_questions', 'eligibility_questions'] as $table) {
            try {
                if (
                    Schema::hasTable($table)
                    && Schema::hasColumn($table, 'question_id')
                    && Schema::hasColumn($table, 'question_text')
                ) {
                    return $table;
                }
            } catch (Throwable $e) {
                logger()->warning('Question table check failed.', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function questionColumns(string $table): array
    {
        try {
            return Schema::getColumnListing($table);
        } catch (Throwable $e) {
            logger()->warning('Question column check failed.', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function questionOrderColumn(array $columns): string
    {
        if (in_array('question_order', $columns, true)) {
            return 'question_order';
        }

        if (in_array('sort_order', $columns, true)) {
            return 'sort_order';
        }

        return 'question_id';
    }

    private function questionSelects(array $columns): array
    {
        $orderColumn = $this->questionOrderColumn($columns);

        return [
            'question_id',
            'question_text',
            in_array('followup_prompt', $columns, true) ? 'followup_prompt' : DB::raw("'' as followup_prompt"),
            in_array('followup_trigger', $columns, true) ? 'followup_trigger' : DB::raw("'' as followup_trigger"),
            "{$orderColumn} as question_order",
            in_array('is_active', $columns, true) ? 'is_active' : DB::raw('1 as is_active'),
        ];
    }

    private function questionWritePayload(array $validated, array $columns, bool $isCreate): array
    {
        $payload = [
            'question_text' => trim($validated['question_text']),
        ];

        if (in_array('followup_prompt', $columns, true)) {
            $payload['followup_prompt'] = trim((string) ($validated['followup_prompt'] ?? ''));
        }

        if (in_array('followup_trigger', $columns, true)) {
            $payload['followup_trigger'] = trim((string) ($validated['followup_trigger'] ?? ''));
        }

        if (in_array('question_order', $columns, true)) {
            $payload['question_order'] = $validated['question_order'];
        } elseif (in_array('sort_order', $columns, true)) {
            $payload['sort_order'] = $validated['question_order'];
        }

        if ($isCreate && in_array('question_type', $columns, true)) {
            $payload['question_type'] = 'yes_no';
        }

        if ($isCreate && in_array('is_disqualifying', $columns, true)) {
            $payload['is_disqualifying'] = false;
        }

        if ($isCreate && in_array('is_active', $columns, true)) {
            $payload['is_active'] = true;
        }

        if ($isCreate && in_array('created_at', $columns, true)) {
            $payload['created_at'] = now();
        }

        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = now();
        }

        return $payload;
    }

    private function emptyQuestionPayload(int $page, int $perPage): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => $page,
                'last_page'    => 1,
                'per_page'     => $perPage,
                'total'        => 0,
                'from'         => 0,
                'to'           => 0,
            ],
            'stats' => [
                'total'    => 0,
                'active'   => 0,
                'inactive' => 0,
            ],
        ];
    }

    private function writeAudit(
        Request $request,
        string $actionType,
        string $description,
        ?int $questionId = null
    ): void {
        try {
            if (! Schema::hasTable('audit_logs')) {
                return;
            }

            $actorName = trim((string) (
                $request->session()->get('admin_full_name')
                    ?: $request->session()->get('admin_username')
                    ?: 'Admin'
            ));
            $actorRole = ucfirst((string) $request->session()->get('admin_role', 'admin'));

            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id'))
                    ? (int) $request->session()->get('admin_id') : null,
                'actor_name'   => $actorName,
                'actor_role'   => $actorRole,
                'action_type'  => $actionType,
                'module_type'  => 'eligibility',
                'target_table' => $this->questionTable() ?? 'eligibility_questions',
                'target_id'    => $questionId,
                'description'  => $description,
                'ip_address'   => $request->ip(),
                'result'       => 'success',
                'metadata'     => null,
                'created_at'   => now(),
            ]);
        } catch (Throwable $e) {
            logger()->warning('Failed to write question audit.', [
                'action_type' => $actionType,
                'question_id' => $questionId,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
