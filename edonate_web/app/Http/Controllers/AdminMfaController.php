<?php

namespace App\Http\Controllers;

use App\Services\AdminMfaService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class AdminMfaController extends Controller
{
    public function __construct(private readonly AdminMfaService $mfa)
    {
    }

    /**
     * Send/resent a number-matching push for the current pending login.
     */
    public function triggerMfaNotification(Request $request): JsonResponse
    {
        $pending = $this->pendingLogin($request);
        if ($pending === null) {
            return response()->json([
                'success' => false,
                'message' => 'Your login verification session has expired. Please log in again.',
            ], 409);
        }

        $adminId = (int) ($pending['admin_id'] ?? 0);
        $challenge = $this->mfa->rotateChallenge($adminId, (string) ($pending['challenge_id'] ?? ''));
        $pending['challenge_id'] = $challenge['id'];
        $pending['prompt_number'] = $challenge['number'];
        $pending['prompt_expires_at'] = $challenge['expires_at'];
        $pending['prompt_available'] = false;
        $request->session()->put('pending_admin_2fa', $pending);

        $delivery = $this->mfa->sendPromptNotification($adminId, $challenge['id']);
        $pending['prompt_available'] = (bool) ($delivery['available'] ?? false);
        $request->session()->put('pending_admin_2fa', $pending);

        return response()->json([
            'success' => true,
            'message' => (string) ($delivery['message'] ?? 'Approval notification refreshed.'),
            'number' => $challenge['number'],
            'available' => (bool) ($delivery['available'] ?? false),
            'expiresAt' => $challenge['expires_at'],
            'challenge' => $challenge['id'],
        ]);
    }

    /**
     * Return the approval state to the login page's polling fallback.
     */
    public function promptStatus(Request $request): JsonResponse
    {
        $pending = $this->pendingLogin($request);
        $challengeId = trim((string) $request->query('challenge', ''));
        if ($pending === null || ! $this->sameChallenge($pending, $challengeId)) {
            return response()->json([
                'success' => false,
                'status' => 'expired',
                'message' => 'Your login verification session has expired. Please log in again.',
            ], 409);
        }

        $challenge = $this->mfa->getChallenge($challengeId);
        if ($challenge === null) {
            return response()->json([
                'success' => false,
                'status' => 'expired',
                'message' => 'Your login verification session has expired. Please log in again.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'status' => (string) ($challenge['status'] ?? 'pending'),
            'challenge' => $challengeId,
        ]);
    }

    /**
     * Complete the existing admin session after a number match is approved.
     * The final authentication work remains in AdminAuthController so the
     * push path shares the same remember-me, audit, and role logic as TOTP.
     */
    public function completePromptLogin(Request $request): RedirectResponse
    {
        $pending = $this->pendingLogin($request);
        if ($pending === null) {
            return redirect()->route('admin.login')
                ->with('error', 'Your login verification session has expired. Please log in again.');
        }

        $challengeId = trim((string) $request->input('challenge', ''));
        if (! $this->sameChallenge($pending, $challengeId)) {
            return redirect()->route('admin.login')
                ->with('error', 'The login approval request is no longer valid.');
        }

        $approved = $this->mfa->consumeApprovedChallenge($challengeId, (int) $pending['admin_id']);
        if ($approved === null) {
            return redirect()->route('admin.login')
                ->with('error', 'Approve the matching number on your registered device first.');
        }

        return app(AdminAuthController::class)->completePromptTwoFactorLogin($request, $pending);
    }

    /**
     * Display the three-choice approval screen opened by the push notification.
     * The endpoint is signed and does not require an already authenticated
     * desktop session because the phone is the second factor device.
     */
    public function showMobileApproval(Request $request, string $challenge): View|RedirectResponse
    {
        $challengeData = $this->mfa->getChallenge($challenge);
        if ($challengeData === null || ! is_numeric($challengeData['admin_id'] ?? null)) {
            abort(410, 'This login approval request has expired.');
        }

        $admin = DB::table('admins')
            ->select('admin_id', 'full_name')
            ->where('admin_id', (int) $challengeData['admin_id'])
            ->first();

        if (! $admin) {
            abort(410, 'This login approval request is no longer available.');
        }

        $number = (int) $challengeData['number'];
        $choices = $this->numberChoices($number, $challenge);

        $expiresAt = (int) $challengeData['expires_at'];

        return view('admin.mfa_mobile_approval', [
            'adminName' => trim((string) ($admin->full_name ?: 'Admin')),
            'choices' => $choices,
            'challenge' => $challenge,
            'expiresAt' => $expiresAt,
            'submitUrl' => URL::temporarySignedRoute(
                'admin.mfa.mobile.approve',
                CarbonImmutable::createFromTimestamp($expiresAt),
                ['challenge' => $challenge]
            ),
            'approvalUrl' => $request->fullUrl(),
        ]);
    }

    /**
     * Verify the selected number and mark the desktop challenge approved.
     */
    public function approveMobile(Request $request, string $challenge): View
    {
        $validated = $request->validate([
            'number' => ['required', 'digits:2'],
        ]);

        $challengeData = $this->mfa->getChallenge($challenge);
        if ($challengeData === null || ! is_numeric($challengeData['admin_id'] ?? null)) {
            abort(410, 'This login approval request has expired.');
        }

        $approved = $this->mfa->approveChallenge(
            $challenge,
            (int) $challengeData['admin_id'],
            (string) $validated['number']
        );

        return view('admin.mfa_mobile_result', [
            'approved' => $approved,
            'message' => $approved
                ? 'This sign-in was approved. You may return to the desktop browser.'
                : 'That number did not match. The desktop sign-in was not approved.',
            'approvalUrl' => $request->fullUrl(),
        ]);
    }

    /**
     * Register the current browser as an admin approval device.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        if (! Schema::hasTable('admin_devices')) {
            return response()->json([
                'success' => false,
                'message' => 'Device registration is not available until migrations are run.',
            ], 409);
        }

        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:512'],
        ]);

        $adminId = (int) $request->session()->get('admin_id', 0);
        if ($adminId <= 0) {
            return response()->json(['success' => false, 'message' => 'Please log in again.'], 401);
        }

        $device = $this->mfa->registerDevice(
            $adminId,
            trim((string) $validated['endpoint']),
            trim((string) $validated['keys']['p256dh']),
            trim((string) $validated['keys']['auth'])
        );

        return response()->json([
            'success' => true,
            'message' => 'This browser is now registered for number-matching approvals.',
            'deviceId' => (int) $device->getKey(),
        ]);
    }

    public function removeDevice(Request $request, int $device): JsonResponse
    {
        $adminId = (int) $request->session()->get('admin_id', 0);
        $removed = $adminId > 0 && $this->mfa->removeDevice($adminId, $device);

        return response()->json([
            'success' => $removed,
            'message' => $removed ? 'Approval device removed.' : 'Approval device not found.',
        ], $removed ? 200 : 404);
    }

    /** @return array<string, mixed>|null */
    private function pendingLogin(Request $request): ?array
    {
        $pending = $request->session()->get('pending_admin_2fa');
        if (! is_array($pending) || (int) ($pending['admin_id'] ?? 0) <= 0) {
            return null;
        }

        if ((int) ($pending['expires_at'] ?? 0) <= now()->timestamp) {
            $this->mfa->forgetChallenge((string) ($pending['challenge_id'] ?? ''));
            $request->session()->forget('pending_admin_2fa');

            return null;
        }

        return $pending;
    }

    /** @param array<string, mixed> $pending */
    private function sameChallenge(array $pending, string $challengeId): bool
    {
        $expected = trim((string) ($pending['challenge_id'] ?? ''));

        return $expected !== ''
            && $challengeId !== ''
            && hash_equals($expected, $challengeId);
    }

    /** @return array<int, string> */
    private function numberChoices(int $correctNumber, string $challengeId): array
    {
        $choices = [$correctNumber];
        $seed = hexdec(substr(hash('sha256', $challengeId), 0, 8));
        while (count($choices) < 3) {
            $candidate = 10 + (($seed + count($choices) * 37) % 90);
            if (! in_array($candidate, $choices, true)) {
                $choices[] = $candidate;
            }
            $seed = ($seed + 17) % 1000000;
        }

        shuffle($choices);

        return array_map(static fn (int $choice): string => str_pad((string) $choice, 2, '0', STR_PAD_LEFT), $choices);
    }
}
