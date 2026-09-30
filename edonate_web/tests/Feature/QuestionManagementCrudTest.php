<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use App\Services\EligibilityEvaluator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuestionManagementCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    public function test_admin_can_create_and_view_question_with_all_screening_fields_and_history_counts(): void
    {
        $this->bypassAdminMiddleware();

        $response = $this->postJson(route('admin.eligibility.questions.store'), $this->questionPayload([
            'question_text' => 'Have you recently received a blood transfusion?',
            'followup_prompt' => 'When did this happen?',
            'followup_trigger' => 'yes',
            'question_order' => 41,
            'is_active' => true,
            'risk_level' => 'temporary_defer',
            'trigger_answer' => 'yes',
            'deferral_days' => 90,
            'recommendation_message' => 'Please wait and consult a health professional.',
        ]));

        $response->assertCreated()
            ->assertJsonPath('question.question_text', 'Have you recently received a blood transfusion?')
            ->assertJsonPath('question.question_order', 41)
            ->assertJsonPath('question.followup_prompt', 'When did this happen?')
            ->assertJsonPath('question.followup_trigger', 'yes')
            ->assertJsonPath('question.risk_level', 'temporary_defer')
            ->assertJsonPath('question.trigger_answer', 'yes')
            ->assertJsonPath('question.deferral_days', 90)
            ->assertJsonPath('question.recommendation_message', 'Please wait and consult a health professional.')
            ->assertJsonPath('question.is_active', true);

        $questionId = (int) $response->json('question_id');
        DB::table('donor_screening_answers')->insert([
            ['eligibility_id' => 1001, 'question_id' => $questionId, 'answer' => 'yes'],
            ['eligibility_id' => 1001, 'question_id' => $questionId, 'answer' => 'yes'],
            ['eligibility_id' => 1002, 'question_id' => $questionId, 'answer' => 'no'],
        ]);
        $snapshotMigration = require database_path('migrations/2026_09_29_000000_add_question_snapshot_to_donor_screening_answers_table.php');
        $snapshotMigration->up();
        $legacySnapshot = DB::table('donor_screening_answers')->where('question_id', $questionId)->value('question_snapshot');
        $this->assertSame('Have you recently received a blood transfusion?', json_decode((string) $legacySnapshot, true)['question_text']);

        $this->getJson(route('admin.eligibility.questions.show', $questionId))
            ->assertOk()
            ->assertJsonPath('question.question_id', $questionId)
            ->assertJsonPath('history.answer_count', 3)
            ->assertJsonPath('history.screening_count', 2);

        $this->assertDatabaseHas('audit_logs', [
            'action_type' => 'question_created',
            'target_table' => 'screening_questions',
            'target_id' => $questionId,
        ]);
    }

    public function test_admin_can_update_question_and_validation_rejects_invalid_decision_fields(): void
    {
        $this->bypassAdminMiddleware();
        $questionId = $this->createQuestion(['question_text' => 'Original question']);
        DB::table('donor_screening_answers')->insert([
            'eligibility_id' => 77,
            'question_id' => $questionId,
            'answer' => 'yes',
            'question_snapshot' => null,
        ]);

        $this->putJson(route('admin.eligibility.questions.update', $questionId), $this->questionPayload([
            'question_text' => 'Updated question text',
            'question_order' => 12,
            'followup_trigger' => 'no',
            'followup_prompt' => 'Please explain your answer.',
            'risk_level' => 'for_review',
            'trigger_answer' => 'no',
            'deferral_days' => null,
            'recommendation_message' => 'Your response will be reviewed.',
            'is_active' => false,
        ]))
            ->assertOk()
            ->assertJsonPath('question.question_text', 'Updated question text')
            ->assertJsonPath('question.risk_level', 'for_review')
            ->assertJsonPath('question.is_active', false);

        $this->assertDatabaseHas('screening_questions', [
            'question_id' => $questionId,
            'question_text' => 'Updated question text',
            'question_order' => 12,
            'followup_prompt' => 'Please explain your answer.',
            'followup_trigger' => 'no',
            'risk_level' => 'for_review',
            'trigger_answer' => 'no',
            'is_active' => 0,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action_type' => 'question_updated', 'target_id' => $questionId]);
        $historySnapshot = DB::table('donor_screening_answers')->where('question_id', $questionId)->value('question_snapshot');
        $this->assertSame('Original question', json_decode((string) $historySnapshot, true)['question_text']);

        $this->putJson(route('admin.eligibility.questions.update', $questionId), $this->questionPayload([
            'question_text' => '',
            'risk_level' => 'temporary_defer',
            'trigger_answer' => null,
            'deferral_days' => null,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['question_text', 'trigger_answer', 'deferral_days']);
    }

    public function test_search_status_counts_and_pagination_use_the_same_filtered_question_set(): void
    {
        $this->bypassAdminMiddleware();
        $this->createQuestion(['question_text' => 'Codex CRUD marker Fever question one', 'question_order' => 1, 'is_active' => true]);
        $this->createQuestion(['question_text' => 'Codex CRUD marker Fever question two', 'question_order' => 2, 'is_active' => true]);
        $this->createQuestion(['question_text' => 'Codex CRUD marker Fever inactive question', 'question_order' => 3, 'is_active' => false]);
        $this->createQuestion(['question_text' => 'Unrelated question', 'question_order' => 4, 'is_active' => true]);

        $this->getJson(route('admin.eligibility.questions.data', [
            'search' => 'Codex CRUD marker',
            'is_active' => '1',
            'per_page' => 1,
            'page' => 99,
        ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.from', 2)
            ->assertJsonPath('meta.to', 2)
            ->assertJsonPath('stats.total', 3)
            ->assertJsonPath('stats.active', 2)
            ->assertJsonPath('stats.inactive', 1)
            ->assertJsonCount(1, 'data');

        $this->getJson(route('admin.eligibility.questions.data', ['search' => 'missing']))
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('stats.total', 0)
            ->assertJsonPath('stats.active', 0)
            ->assertJsonPath('stats.inactive', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_delete_archives_question_preserves_history_and_removes_it_from_new_screening(): void
    {
        $this->bypassAdminMiddleware();
        $questionId = $this->createQuestion([
            'question_text' => 'Risk question to archive',
            'risk_level' => 'auto_reject',
            'trigger_answer' => 'yes',
            'is_active' => true,
        ]);
        DB::table('donor_screening_answers')->insert([
            'eligibility_id' => 500,
            'question_id' => $questionId,
            'answer' => 'yes',
            'followup_answer' => 'Historical explanation',
        ]);

        $this->deleteJson(route('admin.eligibility.questions.destroy', $questionId))
            ->assertOk()
            ->assertJsonPath('archived', true)
            ->assertJsonPath('question.is_active', false);

        $this->assertDatabaseHas('screening_questions', ['question_id' => $questionId, 'is_active' => 0]);
        $this->assertDatabaseHas('donor_screening_answers', [
            'question_id' => $questionId,
            'followup_answer' => 'Historical explanation',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action_type' => 'question_archived', 'target_id' => $questionId]);
        $this->assertSame('eligible', app(EligibilityEvaluator::class)->evaluate([$questionId => 'yes'])['status']);

        $this->getJson(route('admin.eligibility.questions.data', ['is_active' => '0']))
            ->assertOk()
            ->assertJsonFragment(['question_id' => $questionId, 'is_active' => false]);

        $this->deleteJson(route('admin.eligibility.questions.destroy', $questionId))
            ->assertOk()
            ->assertJsonPath('message', 'Question is already archived.');
    }

    public function test_staff_cannot_archive_an_eligibility_question(): void
    {
        $questionId = $this->createQuestion(['question_text' => 'Admin-only question']);
        $staffId = (int) DB::table('admins')->insertGetId([
            'username' => 'question-staff',
            'email' => 'question-staff@example.test',
            'password' => 'not-used-in-test',
            'full_name' => 'Question Staff',
            'role' => 'Staff',
            'is_active' => true,
        ]);

        $this->withoutMiddleware(EnsureAdminAuthenticated::class)
            ->withSession(['admin_id' => $staffId])
            ->deleteJson(route('admin.eligibility.questions.destroy', $questionId))
            ->assertRedirect(route('admin.unauthorized'));

        $this->assertDatabaseHas('screening_questions', ['question_id' => $questionId, 'is_active' => 1]);
    }

    private function bypassAdminMiddleware(): void
    {
        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
    }

    private function createQuestion(array $overrides = []): int
    {
        return (int) DB::table('screening_questions')->insertGetId(array_merge([
            'question_text' => 'Eligibility question',
            'followup_prompt' => null,
            'followup_trigger' => null,
            'question_order' => 1,
            'is_active' => true,
            'risk_level' => 'safe',
            'trigger_answer' => null,
            'deferral_days' => null,
            'recommendation_message' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function questionPayload(array $overrides = []): array
    {
        return array_merge([
            'question_text' => 'Do you feel well today?',
            'followup_prompt' => null,
            'followup_trigger' => null,
            'question_order' => 1,
            'is_active' => true,
            'risk_level' => 'safe',
            'trigger_answer' => null,
            'deferral_days' => null,
            'recommendation_message' => null,
        ], $overrides);
    }
}
