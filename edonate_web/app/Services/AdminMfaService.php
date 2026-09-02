<?php

namespace App\Services;

use App\Events\LoginApprovedEvent;
use App\Models\AdminDevice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class AdminMfaService
{
    public const CACHE_PREFIX = 'admin_mfa:challenge:';

    public const CHALLENGE_TTL_MINUTES = 5;

    /**
     * Create a new one-time number-matching challenge.
     *
     * The number is stored server-side in Laravel's configured cache store;
     * only the opaque challenge id and number needed by the desktop UI are
     * returned to the caller.
     *
     * @return array{id: string, number: string, expires_at: int}
     */
    public function createChallenge(int $adminId): array
    {
        $challengeId = Str::random(64);
        $number = (string) random_int(10, 99);
        $expiresAt = CarbonImmutable::now()->addMinutes(self::CHALLENGE_TTL_MINUTES);

        Cache::put($this->cacheKey($challengeId), [
            'admin_id' => $adminId,
            'number' => $number,
            'status' => 'pending',
            'created_at' => now()->timestamp,
            'expires_at' => $expiresAt->timestamp,
        ], $expiresAt);

        return [
            'id' => $challengeId,
            'number' => $number,
            'expires_at' => $expiresAt->timestamp,
        ];
    }

    /**
     * Return a challenge only while it is still valid.
     *
     * @return array<string, mixed>|null
     */
    public function getChallenge(?string $challengeId): ?array
    {
        $challengeId = trim((string) $challengeId);
        if ($challengeId === '' || ! preg_match('/^[A-Za-z0-9]+$/', $challengeId)) {
            return null;
        }

        $challenge = Cache::get($this->cacheKey($challengeId));
        if (! is_array($challenge)) {
            return null;
        }

        if ((int) ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            Cache::forget($this->cacheKey($challengeId));

            return null;
        }

        return $challenge;
    }

    /**
     * Remove a pending challenge when its desktop session is cancelled or
     * completed through another method such as TOTP.
     */
    public function forgetChallenge(?string $challengeId): void
    {
        $challengeId = trim((string) $challengeId);
        if ($challengeId !== '' && preg_match('/^[A-Za-z0-9]+$/', $challengeId)) {
            Cache::forget($this->cacheKey($challengeId));
        }
    }

    /**
     * Generate a fresh number for an existing pending login.
     *
     * @return array{id: string, number: string, expires_at: int}
     */
    public function rotateChallenge(int $adminId, ?string $oldChallengeId = null): array
    {
        $this->forgetChallenge($oldChallengeId);

        return $this->createChallenge($adminId);
    }

    /**
     * Mark a challenge approved only when it belongs to the admin represented
     * by the signed push link and the selected number matches exactly.
     */
    public function approveChallenge(string $challengeId, int $adminId, string $selectedNumber): bool
    {
        $challenge = $this->getChallenge($challengeId);
        if ($challenge === null || (int) ($challenge['admin_id'] ?? 0) !== $adminId) {
            return false;
        }

        // An approved challenge is one-time use. This prevents a second
        // phone submission from re-broadcasting the same login approval.
        if (($challenge['status'] ?? 'pending') !== 'pending') {
            return false;
        }

        $expected = (string) ($challenge['number'] ?? '');
        $selected = trim($selectedNumber);
        if ($expected === '' || ! hash_equals($expected, $selected)) {
            return false;
        }

        $challenge['status'] = 'approved';
        $challenge['approved_at'] = now()->timestamp;
        $remainingSeconds = max(1, (int) ($challenge['expires_at'] ?? 0) - now()->timestamp);
        Cache::put($this->cacheKey($challengeId), $challenge, now()->addSeconds($remainingSeconds));

        // The cache status is the source of truth and allows the login flow to
        // work on Hostinger without a persistent websocket process. When
        // Reverb/Pusher is configured, this event updates the desktop faster.
        try {
            event(new LoginApprovedEvent($challengeId));
        } catch (Throwable $exception) {
            Log::warning('Admin MFA approval broadcast could not be dispatched.', [
                'challenge' => $challengeId,
                'exception' => $exception::class,
            ]);
        }

        return true;
    }

    /**
     * Consume an approved challenge once. Pending challenges are untouched.
     *
     * @return array<string, mixed>|null
     */
    public function consumeApprovedChallenge(string $challengeId, int $adminId): ?array
    {
        $challenge = $this->getChallenge($challengeId);
        if ($challenge === null || (int) ($challenge['admin_id'] ?? 0) !== $adminId) {
            return null;
        }

        if (($challenge['status'] ?? '') !== 'approved') {
            return null;
        }

        Cache::forget($this->cacheKey($challengeId));

        return $challenge;
    }

    /**
     * Send the login approval link to every registered browser subscription.
     * A missing VAPID configuration or stale device never blocks TOTP login.
     *
     * @return array{available: bool, sent: int, devices: int, message: string}
     */
    public function sendPromptNotification(int $adminId, string $challengeId): array
    {
        if (! Schema::hasTable('admin_devices')) {
            return [
                'available' => false,
                'sent' => 0,
                'devices' => 0,
                'message' => 'Browser approval is not configured yet. Use Google Authenticator instead.',
            ];
        }

        $devices = AdminDevice::query()
            ->where('user_id', $adminId)
            ->get();

        $deviceCount = $devices->count();
        $publicKey = trim((string) config('services.webpush.vapid_public_key', ''));
        $privateKey = trim((string) config('services.webpush.vapid_private_key', ''));

        if ($deviceCount === 0 || $publicKey === '' || $privateKey === '') {
            return [
                'available' => false,
                'sent' => 0,
                'devices' => $deviceCount,
                'message' => $deviceCount === 0
                    ? 'No registered browser approval device was found. Sign in with Google Authenticator once, then register this phone or browser in Settings.'
                    : 'Browser approval is temporarily unavailable. Use Google Authenticator instead.',
            ];
        }

        $challenge = $this->getChallenge($challengeId);
        if ($challenge === null) {
            return [
                'available' => false,
                'sent' => 0,
                'devices' => $deviceCount,
                'message' => 'This login verification session has expired. Please log in again.',
            ];
        }

        $verificationUrl = URL::temporarySignedRoute(
            'admin.mfa.mobile',
            CarbonImmutable::createFromTimestamp((int) $challenge['expires_at']),
            ['challenge' => $challengeId]
        );

        $payload = json_encode([
            'title' => 'eDonate Security Alert',
            'body' => 'Open this notification and choose the matching number to approve your admin sign-in.',
            'icon' => asset('images/edonate-icon.png'),
            'badge' => asset('images/edonate-icon.png'),
            'data' => [
                'url' => $verificationUrl,
                'type' => 'admin_mfa_number_match',
            ],
            // Keep url at the top level too for service-worker compatibility
            // with older push payloads.
            'url' => $verificationUrl,
        ], JSON_UNESCAPED_SLASHES);

        if (! is_string($payload)) {
            return [
                'available' => false,
                'sent' => 0,
                'devices' => $deviceCount,
                'message' => 'Browser approval is temporarily unavailable. Use Google Authenticator instead.',
            ];
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('services.webpush.subject', config('app.url')),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ], [
                'TTL' => 120,
                'urgency' => 'high',
            ], 10);
        } catch (Throwable $exception) {
            Log::error('Admin MFA Web Push could not be initialized.', [
                'admin_id' => $adminId,
                'exception' => $exception::class,
            ]);

            return [
                'available' => false,
                'sent' => 0,
                'devices' => $deviceCount,
                'message' => 'Browser approval is temporarily unavailable. Use Google Authenticator instead.',
            ];
        }

        $sent = 0;
        foreach ($devices as $device) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => (string) $device->endpoint,
                    'publicKey' => (string) $device->public_key,
                    'authToken' => (string) $device->auth_token,
                ]);

                $report = $webPush->sendOneNotification($subscription, $payload);
                if ($report->isSubscriptionExpired()) {
                    $device->delete();
                    continue;
                }

                if ($report->isSuccess()) {
                    $sent++;
                }
            } catch (Throwable $exception) {
                Log::warning('Admin MFA push delivery failed for a registered device.', [
                    'admin_id' => $adminId,
                    'device_id' => (int) $device->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        }

        return [
            'available' => $sent > 0,
            'sent' => $sent,
            'devices' => $deviceCount,
            'message' => $sent > 0
                ? 'Approval notification sent to your registered browser device.'
                : 'The approval notification could not be delivered. Use Google Authenticator instead.',
        ];
    }

    /**
     * Register or update a browser subscription for the current admin.
     * The caller must provide the authenticated admin id; client-supplied ids
     * are deliberately not accepted by this service.
     */
    public function registerDevice(int $adminId, string $endpoint, string $publicKey, string $authToken): AdminDevice
    {
        $device = AdminDevice::query()
            ->where('user_id', $adminId)
            ->where('endpoint', $endpoint)
            ->first();

        if ($device === null) {
            $device = new AdminDevice();
            $device->user_id = $adminId;
            $device->endpoint = $endpoint;
        }

        $device->public_key = $publicKey;
        $device->auth_token = $authToken;
        $device->save();

        return $device;
    }

    public function removeDevice(int $adminId, int $deviceId): bool
    {
        return AdminDevice::query()
            ->where('admin_device_id', $deviceId)
            ->where('user_id', $adminId)
            ->delete() > 0;
    }

    private function cacheKey(string $challengeId): string
    {
        return self::CACHE_PREFIX.$challengeId;
    }
}
