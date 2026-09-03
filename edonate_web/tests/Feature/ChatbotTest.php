<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        // This test must never run its migration against a real donor database.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        (require database_path('migrations/2026_09_03_000000_create_chat_messages_table.php'))->up();

        config([
            'chatbot.api_key' => 'test-gemini-key',
            'chatbot.model' => 'gemini-3.6-flash',
            'chatbot.requests_per_minute' => 10,
            'chatbot.requests_per_hour' => 100,
        ]);
        Http::preventStrayRequests();
        $this->withSession(['chat_test_browser' => 'first']);
        $this->withCredentials()->withCookie(
            config('session.cookie'),
            $this->app['session']->getId()
        );
    }

    public function test_all_history_is_sent_in_order_and_reloaded_from_the_database(): void
    {
        $sessionId = (string) Str::uuid();
        Http::fakeSequence(self::ENDPOINT)
            ->push($this->answer('Nice to meet you, Alex.'))
            ->push($this->answer('You said your name is Alex.'));

        $this->postJson(route('chat.store'), ['session_id' => $sessionId, 'message' => 'My name is Alex.'])
            ->assertOk()->assertJsonPath('reply', 'Nice to meet you, Alex.');
        $this->postJson(route('chat.store'), ['session_id' => $sessionId, 'message' => 'What is my name?'])
            ->assertOk()->assertJsonPath('reply', 'You said your name is Alex.');

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === self::ENDPOINT
            && $request->hasHeader('x-goog-api-key', 'test-gemini-key')
            && $request['system_instruction'] === [
                'parts' => [['text' => config('chatbot.system_instruction')]],
            ]
            && ! array_key_exists('systemInstruction', $request->data())
            && $request['contents'] === [
                ['role' => 'user', 'parts' => [['text' => 'My name is Alex.']]],
                ['role' => 'model', 'parts' => [['text' => 'Nice to meet you, Alex.']]],
                ['role' => 'user', 'parts' => [['text' => 'What is my name?']]],
            ]);

        $this->getJson(route('chat.history', ['session_id' => $sessionId]))
            ->assertOk()->assertJsonCount(4, 'messages')
            ->assertJsonPath('messages.3.message', 'You said your name is Alex.')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('chat_messages', 4);
    }

    public function test_the_same_uuid_in_another_browser_cannot_read_or_extend_a_conversation(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->answer('Hello.'))]);
        $sessionId = (string) Str::uuid();
        $this->postJson(route('chat.store'), ['session_id' => $sessionId, 'message' => 'Private first chat.'])
            ->assertOk();

        $this->app['session']->invalidate();
        $this->withSession(['chat_test_browser' => 'second']);
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        $this->getJson(route('chat.history', ['session_id' => $sessionId]))
            ->assertOk()->assertExactJson(['messages' => []]);
        $this->postJson(route('chat.store'), ['session_id' => $sessionId, 'message' => 'New visitor.'])
            ->assertOk();

        $this->assertSame(2, ChatMessage::distinct()->count('session_id'));
        Http::assertSent(fn (ClientRequest $request): bool => $request['contents'] === [
            ['role' => 'user', 'parts' => [['text' => 'New visitor.']]],
        ]);
    }

    public function test_invalid_input_is_rejected_before_storage_or_api_usage(): void
    {
        Http::fake();
        $this->postJson(route('chat.store'), ['session_id' => 'arbitrary-id', 'message' => ' '])
            ->assertUnprocessable()->assertJsonValidationErrors(['session_id', 'message']);
        $this->postJson(route('chat.store'), [
            'session_id' => (string) Str::uuid(),
            'message' => str_repeat('a', 4001),
        ])->assertUnprocessable()->assertJsonValidationErrors('message');
        Http::assertNothingSent();
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_api_failure_rolls_back_only_the_new_exchange_and_releases_the_lock(): void
    {
        $sessionId = (string) Str::uuid();
        Http::fakeSequence(self::ENDPOINT)
            ->push($this->answer('First reply.'))
            ->push(['error' => ['message' => 'Provider details must stay private.']], 503)
            ->push($this->answer('Retry reply.'));

        $this->postJson(route('chat.store'), ['session_id' => $sessionId, 'message' => 'First question.'])
            ->assertOk();
        $this->postJson(route('chat.store'), ['session_id' => $sessionId, 'message' => 'Second question.'])
            ->assertStatus(503)->assertDontSee('Provider details');
        $this->assertDatabaseCount('chat_messages', 2);

        $this->postJson(route('chat.store'), ['session_id' => $sessionId, 'message' => 'Second question.'])
            ->assertOk();
        $this->assertDatabaseCount('chat_messages', 4);
    }

    public function test_connection_failure_does_not_leave_an_unanswered_user_turn(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('Test connection timeout.');
        });
        $this->postJson(route('chat.store'), [
            'session_id' => (string) Str::uuid(), 'message' => 'Hello.',
        ])->assertStatus(504);
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_blocked_content_is_not_returned_or_persisted(): void
    {
        $answer = $this->answer('Do not return this blocked text.');
        $answer['candidates'][0]['finishReason'] = 'SAFETY';
        Http::fake([self::ENDPOINT => Http::response($answer)]);

        $this->postJson(route('chat.store'), [
            'session_id' => (string) Str::uuid(), 'message' => 'Hello.',
        ])->assertStatus(502)->assertDontSee('blocked text');
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_missing_key_and_full_conversations_do_not_consume_api_calls(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->answer('Hello.'))]);
        config(['chatbot.api_key' => '']);
        $sessionId = (string) Str::uuid();
        $this->postJson(route('chat.store'), ['session_id' => $sessionId, 'message' => 'Hello.'])
            ->assertStatus(503);
        Http::assertNothingSent();

        config(['chatbot.api_key' => 'test-gemini-key', 'chatbot.max_history_messages' => 2]);
        $this->postJson(route('chat.store'), ['session_id' => $sessionId, 'message' => 'Hello.'])
            ->assertOk();
        $this->postJson(route('chat.store'), ['session_id' => $sessionId, 'message' => 'One more.'])
            ->assertUnprocessable();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('chat_messages', 2);
    }

    public function test_changing_chat_uuids_does_not_bypass_rate_limits(): void
    {
        config(['chatbot.requests_per_minute' => 1]);
        Http::fake([self::ENDPOINT => Http::response($this->answer('Hello.'))]);
        $this->postJson(route('chat.store'), [
            'session_id' => (string) Str::uuid(), 'message' => 'Hello.',
        ])->assertOk();
        $this->postJson(route('chat.store'), [
            'session_id' => (string) Str::uuid(), 'message' => 'Hello again.',
        ])->assertTooManyRequests();
        Http::assertSentCount(1);
    }

    public function test_chat_post_requires_csrf_outside_the_test_bypass(): void
    {
        Http::fake();
        $this->app->instance('env', 'production');
        $this->postJson(route('chat.store'), [
            'session_id' => (string) Str::uuid(), 'message' => 'Hello.',
        ])->assertStatus(419);
        Http::assertNothingSent();
    }

    public function test_an_in_progress_reply_blocks_a_second_send_without_an_api_call(): void
    {
        Http::fake();
        $sessionId = (string) Str::uuid();
        $key = hash('sha256', $this->app['session']->getId().'|'.$sessionId);
        $lock = Cache::lock('chatbot:'.$key, 60);
        $this->assertTrue($lock->get());

        try {
            $this->postJson(route('chat.store'), [
                'session_id' => $sessionId, 'message' => 'A simultaneous message.',
            ])->assertStatus(409);
            Http::assertNothingSent();
            $this->assertDatabaseCount('chat_messages', 0);
        } finally {
            $lock->release();
        }
    }

    public function test_the_widget_renders_once_without_exposing_the_api_key(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render('<x-chatbot-widget /><x-chatbot-widget />');

        $this->assertSame(1, substr_count($html, 'id="edonate-chatbot"'));
        $this->assertStringContainsString('data-endpoint="/api/chat"', $html);
        $this->assertStringContainsString('aria-label="Send message"', $html);
        $this->assertStringContainsString('body.textContent = text;', $html);
        $this->assertStringNotContainsString('test-gemini-key', $html);
    }

    private function answer(string $text): array
    {
        return [
            'candidates' => [[
                'content' => ['role' => 'model', 'parts' => [['text' => $text]]],
                'finishReason' => 'STOP',
            ]],
        ];
    }
}
