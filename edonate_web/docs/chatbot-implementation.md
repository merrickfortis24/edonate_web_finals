# eDonate floating Gemini chatbot — complete implementation

The widget is installed in the admin layout, donor portal layout, and standalone donor dashboard. It uses only Blade, native HTML/CSS, and Vanilla JavaScript; the widget does not depend on Bootstrap, Tailwind, React, or Vue.

## Model availability

Google has retired older Gemini Flash models over time. This implementation defaults to the stable `gemini-3.6-flash`, which is available to the configured key, and allows a different supported model through `GEMINI_MODEL`. See the [Google model documentation](https://ai.google.dev/gemini-api/docs/models/gemini-3.6-flash) and [model lifecycle documentation](https://ai.google.dev/gemini-api/docs/deprecations).

## Configuration and deployment

In the application's private `.env`, set `GEMINI_API_KEY` to your Google AI Studio API key. Do not commit that value or expose it through a `VITE_*` variable. The key is read only on the server; its value is never rendered into the widget or written to logs.

```dotenv
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.6-flash
```

The empty API-key value above must be filled with your own credential; it is not a working key. The code reads the key through Laravel configuration so `config:cache` works, with the requested `env('GEMINI_API_KEY')` fallback.

Run from the Laravel application directory (the nested `edonate_web` directory):

```shell
php artisan migrate --path=database/migrations/2026_09_03_000000_create_chat_messages_table.php
php artisan config:cache
php artisan view:cache
```

The new migration has already been applied to the local MySQL database. Run it with `--force` when deploying to production. Do not use `migrate:fresh` or drop existing tables. No Node build or new Composer dependency is needed for this widget.

The HTTP contract is:

- `POST /api/chat`: JSON with `session_id` (UUID) and `message`; returns `{"reply":"..."}`.
- `GET /api/chat?session_id=...`: returns `{"messages":[{"role":"user","message":"..."},{"role":"model","message":"..."}]}`.
- Both routes use Laravel's browser session. POST requests require the CSRF token emitted into the widget, and fetch includes same-origin cookies.

## Behavior and safeguards

The client UUID lives in `sessionStorage`. The database's `session_id` is a SHA-256 identifier derived from both that UUID and the server's Laravel session. Merely guessing or copying a chat UUID cannot read or append to another browser's history. History survives reloads and navigation in the same tab/session; logging out, rotating the Laravel session, or letting it expire ends access to that conversation.

Every stored turn is sent in chronological order, using the ID as a tie-breaker. A new turn is rejected if history already has 100 messages or if history plus the new message exceeds 100,000 characters, instead of silently truncating history. The plus button starts a fresh UUID; it does not delete old database records.

Requests have message-length limits, IP rate limits, a bounded provider timeout, and a per-conversation cache lock. A failed provider call rolls back only the new exchange. There are no automatic provider retries that could duplicate charges. The UI restores an unsent message after a failure; after a lost network response, reload the page and open the chat before retrying if uncertain whether the server completed the turn.

User and model text use `textContent`, never `innerHTML`. The API key, provider error bodies, prompts, and model replies are not written to application error logs. The assistant has no tools or access to donor/admin records, and the UI discloses that messages are saved and processed by Google.

For production, use HTTPS, secure session cookies, `APP_DEBUG=false`, provider quotas/billing limits, and a shared lock-capable cache if running multiple app instances. Establish a retention/deletion policy for chat rows and do not use this general assistant to collect private health information. No existing donor/authentication tables or login flows were changed by this feature.

## Complete source files

The following paths are relative to the Laravel application directory. These are the complete installed files, not pseudocode.

### database/migrations/2026_09_03_000000_create_chat_messages_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('session_id', 64);
            $table->enum('role', ['user', 'model']);
            $table->longText('message');
            $table->timestamps();
            $table->index(['session_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
```

### app/Models/ChatMessage.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = [
        'session_id',
        'role',
        'message',
    ];
}
```

### config/chatbot.php

```php
<?php

return [
    // Read env here so php artisan config:cache also preserves the API key.
    'api_key' => env('GEMINI_API_KEY', ''),
    // Gemini 1.5 Flash was shut down on September 29, 2025.
    'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    'max_message_length' => 4000,
    'max_history_messages' => 100,
    'max_history_characters' => 100000,
    'max_output_tokens' => 2048,
    'timeout_seconds' => 30,
    'requests_per_minute' => 10,
    'requests_per_hour' => 100,
    'system_instruction' => 'You are the eDonate assistant for the City Health Office, Lipa City. '
        .'Help with general blood donation questions and using the donor and admin portals. '
        .'Answer concisely in the language used by the visitor, using plain text. '
        .'You cannot access records, book appointments, or change account settings. '
        .'Do not invent office schedules or claim to have checked a donor record. '
        .'Refer personal medical or donation-eligibility decisions to the City Health Office. '
        .'Never ask for passwords, authentication codes, or private medical records.',
];
```

### app/Http/Controllers/ChatbotController.php

```php
<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

class ChatbotController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate(['session_id' => ['required', 'uuid']]);

        try {
            $messages = $this->historyQuery($this->conversationKey($request, $validated['session_id']))
                ->get(['role', 'message']);

            return $this->respond(['messages' => $messages]);
        } catch (Throwable $exception) {
            Log::warning('Chat history could not be loaded.', ['type' => $exception::class]);

            return $this->respond(['message' => 'Chat history is unavailable. Please try again.'], 503);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'message' => ['required', 'string', 'max:'.config('chatbot.max_message_length')],
        ]);

        // Keep the key server-side and support Laravel's cached configuration.
        $apiKey = trim((string) config('chatbot.api_key', env('GEMINI_API_KEY', '')));
        $model = (string) config('chatbot.model');
        if ($apiKey === '' || ! preg_match('/\Agemini-[a-z0-9.-]+\z/', $model)) {
            return $this->respond(['message' => 'The assistant is not available yet. Please try again later.'], 503);
        }

        $sessionId = $this->conversationKey($request, $validated['session_id']);
        $message = trim($validated['message']);

        try {
            // Serialize turns so simultaneous sends cannot mix conversation order.
            $result = Cache::lock('chatbot:'.$sessionId, (int) config('chatbot.timeout_seconds') + 30)
                ->get(function () use ($sessionId, $message, $apiKey, $model): JsonResponse {
                    $history = $this->historyQuery($sessionId)->get();
                    if ($history->count() >= (int) config('chatbot.max_history_messages')
                        || $history->sum(fn (ChatMessage $row): int => mb_strlen($row->message))
                            + mb_strlen($message) > (int) config('chatbot.max_history_characters')) {
                        return $this->respond([
                            'message' => 'This conversation is full. Start a new chat to continue.',
                        ], 422);
                    }

                    // Commit a complete exchange, or roll back this new user message on failure.
                    return DB::transaction(function () use ($sessionId, $message, $apiKey, $model): JsonResponse {
                        ChatMessage::create([
                            'session_id' => $sessionId,
                            'role' => 'user',
                            'message' => $message,
                        ]);

                        // Include every stored turn, including the user message just saved.
                        $contents = $this->historyQuery($sessionId)->get()
                            ->map(fn (ChatMessage $row): array => [
                                'role' => $row->role,
                                'parts' => [['text' => $row->message]],
                            ])->all();

                        $response = Http::acceptJson()
                            ->withHeaders(['x-goog-api-key' => $apiKey])
                            ->connectTimeout(5)
                            ->timeout((int) config('chatbot.timeout_seconds'))
                            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                                'contents' => $contents,
                                // The Gemini REST endpoint expects snake_case here.
                                'system_instruction' => [
                                    'parts' => [['text' => config('chatbot.system_instruction')]],
                                ],
                                'generationConfig' => [
                                    'maxOutputTokens' => (int) config('chatbot.max_output_tokens'),
                                ],
                            ])->throw();

                        $reply = trim(collect($response->json('candidates.0.content.parts', []))
                            ->filter(fn ($part): bool => is_array($part)
                                && is_string($part['text'] ?? null)
                                && ! ($part['thought'] ?? false))
                            ->pluck('text')->implode("\n"));

                        if ($reply === '' || ! in_array(
                            $response->json('candidates.0.finishReason'),
                            ['STOP', 'MAX_TOKENS'],
                            true
                        )) {
                            throw new UnexpectedValueException('No usable assistant response.');
                        }

                        ChatMessage::create([
                            'session_id' => $sessionId,
                            'role' => 'model',
                            'message' => $reply,
                        ]);

                        return $this->respond(['reply' => $reply]);
                    });
                });

            return $result ?: $this->respond([
                'message' => 'A reply is already being generated for this chat. Please wait.',
            ], 409);
        } catch (ConnectionException $exception) {
            Log::warning('Gemini connection failed.', ['type' => $exception::class]);

            return $this->respond(['message' => 'The assistant took too long to respond. Please try again.'], 504);
        } catch (RequestException $exception) {
            // Do not log prompts, response bodies, or the API key.
            $status = $exception->response->status();
            $providerStatus = $exception->response->json('error.status');
            Log::warning('Gemini request failed.', [
                'status' => $status,
                'provider_status' => is_string($providerStatus) ? $providerStatus : null,
            ]);

            $message = match ($status) {
                400 => 'Gemini rejected the request. Check the selected model and try again.',
                401, 403 => 'The Gemini API key was rejected. Check GEMINI_API_KEY and try again.',
                404 => 'The configured Gemini model was not found. Check GEMINI_MODEL and try again.',
                429 => 'The Gemini usage limit has been reached. Please try again later.',
                default => 'The assistant is temporarily unavailable. Please try again later.',
            };

            return $this->respond(['message' => $message], 503);
        } catch (UnexpectedValueException $exception) {
            return $this->respond(['message' => 'The assistant could not answer that message. Please rephrase it.'], 502);
        } catch (Throwable $exception) {
            Log::error('Chat request failed.', ['type' => $exception::class]);

            return $this->respond(['message' => 'Your message could not be processed. Please try again.'], 503);
        }
    }

    private function conversationKey(Request $request, string $clientSessionId): string
    {
        // A client UUID alone is not authorization. Bind it to the browser's Laravel session.
        return hash('sha256', $request->session()->getId().'|'.strtolower($clientSessionId));
    }

    private function historyQuery(string $sessionId): Builder
    {
        return ChatMessage::where('session_id', $sessionId)->orderBy('created_at')->orderBy('id');
    }

    private function respond(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'no-store');
    }
}
```

### routes/api.php

```php
<?php

use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

// This browser widget uses Laravel's session and CSRF cookie at /api/chat.
Route::middleware('web')->group(function (): void {
    Route::get('/chat', [ChatbotController::class, 'history'])
        ->middleware('throttle:public-api')
        ->name('chat.history');

    Route::post('/chat', [ChatbotController::class, 'store'])
        ->middleware('throttle:chatbot')
        ->name('chat.store');
});
```

### resources/views/components/chatbot-widget.blade.php

```blade
@once
<div id="edonate-chatbot"
     data-endpoint="{{ route('chat.store', [], false) }}"
     data-history="{{ route('chat.history', [], false) }}"
     data-csrf="{{ csrf_token() }}">
    <button type="button" class="ec-launch" aria-label="Open eDonate assistant"
            aria-controls="ec-window" aria-expanded="false">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/></svg>
    </button>

    <section id="ec-window" class="ec-window" role="dialog" aria-modal="false"
             aria-labelledby="ec-title" hidden>
        <header class="ec-header">
            <div>
                <h2 id="ec-title">eDonate Assistant</h2>
                <p>Ask about eDonate</p>
            </div>
            <button type="button" class="ec-icon ec-new" aria-label="Start a new chat" title="New chat">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <button type="button" class="ec-icon ec-close" aria-label="Close chat">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg>
            </button>
        </header>

        <div class="ec-messages" role="log" aria-live="polite"
             aria-relevant="additions text" aria-label="Chat messages"></div>
        <p class="ec-notice" role="status" hidden></p>

        <form class="ec-form">
            <label for="ec-input" class="ec-sr-only">Your message</label>
            <input id="ec-input" name="message" type="text"
                   maxlength="{{ config('chatbot.max_message_length', 4000) }}"
                   placeholder="Type a message…" autocomplete="off" required>
            <button type="submit" class="ec-icon ec-send" aria-label="Send message">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/></svg>
            </button>
        </form>
        <p class="ec-footnote">Messages are saved and processed by Google Gemini.</p>
    </section>
</div>

<style>
    #edonate-chatbot {
        position: fixed;
        right: max(16px, env(safe-area-inset-right));
        bottom: max(16px, env(safe-area-inset-bottom));
        z-index: 1040;
        color: #f3f4f6;
        font: 14px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif;
        text-align: left;
    }
    #edonate-chatbot *, #edonate-chatbot *::before, #edonate-chatbot *::after { box-sizing: border-box; }
    #edonate-chatbot [hidden] { display: none !important; }
    #edonate-chatbot button, #edonate-chatbot input { font: inherit; margin: 0; }
    #edonate-chatbot button { display: inline-flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; border: 0; }
    #edonate-chatbot button:disabled { opacity: .45; cursor: wait; }
    #edonate-chatbot button:focus-visible, #edonate-chatbot input:focus-visible { outline: 2px solid #fca5a5; outline-offset: 3px; }
    #edonate-chatbot svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    #edonate-chatbot .ec-launch { width: 58px; height: 58px; border-radius: 50%; background: #b91c1c; box-shadow: 0 8px 28px #0005; }
    #edonate-chatbot .ec-launch:hover { background: #991b1b; }
    #edonate-chatbot .ec-launch svg { width: 28px; height: 28px; }
    #edonate-chatbot .ec-window {
        display: flex;
        flex-direction: column;
        width: min(380px, calc(100vw - 32px));
        height: min(560px, calc(100vh - 32px));
        height: min(560px, calc(100dvh - 32px));
        overflow: hidden;
        border: 1px solid #555665;
        border-radius: 18px;
        background: #343541;
        box-shadow: 0 16px 48px #0006;
    }
    #edonate-chatbot .ec-header { display: flex; align-items: center; gap: 8px; padding: 16px; background: #282934; border-bottom: 1px solid #484957; flex-shrink: 0; }
    #edonate-chatbot .ec-header > div { flex: 1; min-width: 0; }
    #edonate-chatbot .ec-header h2 { margin: 0; font: 700 16px/1.4 system-ui, sans-serif; color: #fff; }
    #edonate-chatbot .ec-header p { margin: 3px 0 0; color: #c2c3cc; font-size: 12px; }
    #edonate-chatbot .ec-icon { width: 36px; height: 36px; padding: 8px; flex-shrink: 0; border-radius: 8px; background: transparent; }
    #edonate-chatbot .ec-icon:hover { background: #494a59; }
    #edonate-chatbot .ec-messages { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; scrollbar-color: #737482 #343541; }
    #edonate-chatbot .ec-message { padding: 16px; border-bottom: 1px solid #ffffff0a; background: #343541; }
    #edonate-chatbot .ec-message--user { background: #2e2f3a; }
    #edonate-chatbot .ec-message strong { display: block; color: #fca5a5; font-size: 11px; letter-spacing: .05em; margin-bottom: 5px; }
    #edonate-chatbot .ec-message p { margin: 0; color: #f3f4f6; white-space: pre-wrap; overflow-wrap: anywhere; }
    #edonate-chatbot .ec-notice { margin: 0; padding: 10px 16px; color: #fecaca; background: #472f37; font-size: 12px; overflow-wrap: anywhere; }
    #edonate-chatbot .ec-form { display: flex; align-items: center; gap: 8px; padding: 12px; border-top: 1px solid #555665; background: #282934; flex-shrink: 0; }
    #edonate-chatbot .ec-form input { flex: 1; min-width: 0; width: 100%; padding: 10px 12px; color: #fff; background: #40414f; border: 1px solid #696a79; border-radius: 9px; font-size: 16px; }
    #edonate-chatbot .ec-form input::placeholder { color: #c2c3cc; opacity: 1; }
    #edonate-chatbot .ec-send { background: #b91c1c; width: 42px; height: 42px; }
    #edonate-chatbot .ec-send:hover { background: #991b1b; }
    #edonate-chatbot .ec-footnote { margin: 0; padding: 0 12px 10px; color: #c2c3cc; background: #282934; font-size: 10px; text-align: center; flex-shrink: 0; }
    #edonate-chatbot .ec-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
</style>

<script>
(() => {
    'use strict';
    const root = document.getElementById('edonate-chatbot');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = 'true';

    const launch = root.querySelector('.ec-launch');
    const panel = root.querySelector('.ec-window');
    const close = root.querySelector('.ec-close');
    const newChat = root.querySelector('.ec-new');
    const log = root.querySelector('.ec-messages');
    const form = root.querySelector('.ec-form');
    const input = root.querySelector('#ec-input');
    const send = root.querySelector('.ec-send');
    const notice = root.querySelector('.ec-notice');
    const storageKey = 'edonate.chat.session';
    let busy = false;
    let loaded = false;

    function uuid() {
        if (globalThis.crypto?.randomUUID) return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
        return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
    }

    function saveSession(id) {
        try { sessionStorage.setItem(storageKey, id); } catch (error) { /* Memory-only mode when storage is blocked. */ }
        return id;
    }

    let sessionId;
    try { sessionId = sessionStorage.getItem(storageKey); } catch (error) { sessionId = null; }
    if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(sessionId || '')) {
        sessionId = saveSession(uuid());
    }

    function scrollToBottom() { log.scrollTop = log.scrollHeight; }
    function showNotice(text = '') { notice.textContent = text; notice.hidden = !text; }
    function setBusy(value) {
        busy = value;
        send.disabled = value || !loaded;
        input.readOnly = value || !loaded;
        newChat.disabled = value;
        log.setAttribute('aria-busy', String(value));
    }

    function appendMessage(role, text) {
        const row = document.createElement('div');
        row.className = role === 'user' ? 'ec-message ec-message--user' : 'ec-message';
        const label = document.createElement('strong');
        label.textContent = role === 'user' ? 'YOU' : 'EDONATE ASSISTANT';
        const body = document.createElement('p');
        body.textContent = text; // Never render visitor or AI content as HTML.
        row.append(label, body);
        log.append(row);
        scrollToBottom();
        return row;
    }

    async function request(url, options = {}) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 45000);
        try {
            const response = await fetch(url, {
                ...options,
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': root.dataset.csrf
                }
            });
            const data = await response.json().catch(() => ({}));
            if (response.status === 419) throw new Error('Your session expired. Refresh this page to continue.');
            if (!response.ok) throw new Error(data.message || 'Chat is unavailable. Please try again.');
            return data;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('The request timed out. Please try again.');
            throw error;
        } finally {
            clearTimeout(timer);
        }
    }

    async function loadHistory() {
        setBusy(true);
        showNotice('Loading conversation…');
        try {
            const url = new URL(root.dataset.history, window.location.origin);
            url.searchParams.set('session_id', sessionId);
            const data = await request(url);
            if (!Array.isArray(data.messages)) throw new Error('Chat history could not be loaded.');
            log.replaceChildren();
            data.messages.forEach(item => {
                if (['user', 'model'].includes(item.role) && typeof item.message === 'string') {
                    appendMessage(item.role, item.message);
                }
            });
            if (!data.messages.length) appendMessage('model', 'Hi! How can I help you with eDonate?');
            loaded = true;
            showNotice();
        } catch (error) {
            showNotice(error.message + ' Reopen chat to retry, or start a new chat.');
        } finally {
            setBusy(false);
        }
    }

    launch.addEventListener('click', async () => {
        panel.hidden = false;
        launch.hidden = true;
        launch.setAttribute('aria-expanded', 'true');
        if (!loaded && !busy) await loadHistory();
        if (!panel.hidden) input.focus();
        scrollToBottom();
    });

    function closeChat() {
        panel.hidden = true;
        launch.hidden = false;
        launch.setAttribute('aria-expanded', 'false');
        launch.focus();
    }
    close.addEventListener('click', closeChat);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) closeChat();
    });

    newChat.addEventListener('click', () => {
        if (busy) return;
        sessionId = saveSession(uuid());
        loaded = true;
        log.replaceChildren();
        appendMessage('model', 'Hi! How can I help you with eDonate?');
        showNotice();
        input.value = '';
        setBusy(false);
        input.focus();
    });

    // Form submission handles both the Send button and Enter.
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.isComposing) event.preventDefault();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || busy || !loaded) return;

        const userRow = appendMessage('user', message);
        input.value = '';
        showNotice();
        const typing = appendMessage('model', 'AI is typing…');
        setBusy(true);

        try {
            const data = await request(root.dataset.endpoint, {
                method: 'POST',
                body: JSON.stringify({ session_id: sessionId, message })
            });
            if (typeof data.reply !== 'string' || !data.reply.trim()) {
                throw new Error('The assistant returned an empty response.');
            }
            typing.remove();
            appendMessage('model', data.reply);
        } catch (error) {
            typing.remove();
            userRow.remove();
            input.value = message;
            showNotice(error.message);
        } finally {
            setBusy(false);
            scrollToBottom();
            if (!panel.hidden) input.focus();
        }
    });
})();
</script>
@endonce
```

## Integration in existing files

### bootstrap/app.php

The existing routing registration now includes the API route file:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

### app/Providers/AppServiceProvider.php

The following complete limiter definition is installed inside the existing `registerRateLimiters()` method. Its `clientIp()` helper and the `RateLimiter`, `Limit`, and `Request` imports already exist in this provider.

```php
RateLimiter::for('chatbot', function (Request $request): array {
    return [
        Limit::perMinute((int) config('chatbot.requests_per_minute', 10))
            ->by('chat-minute:'.$this->clientIp($request)),
        Limit::perHour((int) config('chatbot.requests_per_hour', 100))
            ->by('chat-hour:'.$this->clientIp($request)),
    ];
});
```

### resources/views/layouts/admin.blade.php

The existing admin content section now includes the widget outside its page wrapper:

```blade
@section('content')
    <div class="edonate-admin-page {{ $adminPageClass }}">
        @yield('main_content')
    </div>
    <x-chatbot-widget />
@stop
```

### resources/views/layouts/donor-portal.blade.php and resources/views/donor/dashboard.blade.php

Each file now includes this immediately before `</body>`:

```blade
<x-chatbot-widget />
```

## Verification

Automated coverage is in `tests/Feature/ChatbotTest.php` and uses only an in-memory SQLite database and fake Gemini responses. It covers ordered conversational memory, session isolation, input validation, missing configuration, request/content failures, database rollback, conversation limits, rate limits, CSRF, concurrent sends, and keeping the key out of the rendered widget.

```shell
php artisan test tests/Feature/ChatbotTest.php
php artisan test tests/Feature/AdminLteLayoutTest.php tests/Feature/RateLimitingTest.php
```

Verified: 11 chatbot tests (79 assertions), plus 23 existing layout/rate-limit tests (299 assertions). Browser checks verified the launcher, close/Escape, click/Enter sending, typing indicator, database history after reload, failed-send recovery, new conversations, plain-text rendering, and a 390px mobile layout. The temporary preview used the actual controller/component with a separate test database and fake provider replies, and was removed afterward. A live generated reply was not sent during testing to avoid consuming API quota.
