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
