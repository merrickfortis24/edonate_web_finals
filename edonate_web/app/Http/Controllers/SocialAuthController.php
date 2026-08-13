<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\DonorAuthentication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SocialAuthController extends Controller
{
    /**
     * Handle Firebase Google Sign-In from frontend.
     */
    public function handleGoogleLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'uid' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'full_name' => ['nullable', 'string', 'max:200'],
            'terms_accepted' => ['accepted'],
        ]);

        try {
            $firebaseAuth = app('firebase.auth');
            $verifiedToken = $firebaseAuth->verifyIdToken($validated['id_token']);
            $claims = $verifiedToken->claims();

            $tokenUid = (string) $claims->get('sub');
            $tokenEmail = (string) $claims->get('email');
            $tokenEmailVerified = (bool) $claims->get('email_verified', false);
            $tokenName = (string) $claims->get('name', '');

            if ($tokenUid !== $validated['uid']) {
                return response()->json([
                    'message' => 'Token UID mismatch.',
                ], 401);
            }

            if (strcasecmp($tokenEmail, $validated['email']) !== 0) {
                return response()->json([
                    'message' => 'Token email mismatch.',
                ], 401);
            }

            if (!$tokenEmailVerified) {
                return response()->json([
                    'message' => 'Google account email is not verified.',
                ], 422);
            }

            $displayName = trim($validated['full_name'] ?: $tokenName);
            [$firstName, $lastName] = $this->splitName($displayName);

            $auth = DonorAuthentication::query()
                ->where('email', $tokenEmail)
                ->first();

            if ($auth) {
                $existingDonor = Donor::query()->find($auth->donor_id);
                if ($existingDonor && Schema::hasColumn('donors', 'is_active') && !$existingDonor->is_active) {
                    return response()->json([
                        'message' => 'Unable to complete Google sign-in with these credentials.',
                    ], 422);
                }
            }

            if (!$auth) {
                DB::beginTransaction();

                try {
                    $donor = Donor::query()->create([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'date_registered' => now(),
                    ]);

                    $auth = DonorAuthentication::query()->create([
                        'donor_id' => $donor->donor_id,
                        'email' => $tokenEmail,
                        'password' => Hash::make(Str::random(64)),
                        'is_verified' => true,
                        'verification_token' => null,
                        'verification_sent_at' => now(),
                        'verified_at' => now(),
                        'created_at' => now(),
                    ]);

                    DB::commit();
                } catch (Throwable $exception) {
                    DB::rollBack();
                    throw $exception;
                }
            } else {
                DonorAuthentication::query()
                    ->where('auth_id', $auth->auth_id)
                    ->update([
                        'is_verified' => true,
                        'verified_at' => now(),
                        'verification_token' => null,
                        'verification_sent_at' => now(),
                    ]);

                $auth->refresh();
            }

            $donor = Donor::query()->find($auth->donor_id);

            if ($donor && Schema::hasColumn('donors', 'is_active') && !$donor->is_active) {
                return response()->json([
                    'message' => 'Unable to complete Google sign-in with these credentials.',
                ], 422);
            }

            $request->session()->regenerate();
            $request->session()->put([
                'donor_auth_id' => $auth->auth_id,
                'donor_id' => $auth->donor_id,
                'donor_email' => $auth->email,
                'donor_name' => $donor ? trim($donor->first_name . ' ' . $donor->last_name) : null,
                'auth_provider' => 'google',
                'terms_accepted' => true,
            ]);

            return response()->json([
                'message' => 'Google sign-in successful.',
                'redirect_url' => url('/dashboard'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to complete Google sign-in right now.',
            ], 500);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        if ($clean === '') {
            return ['Google', 'User'];
        }

        $parts = explode(' ', $clean, 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '-';

        return [$firstName, $lastName];
    }
}
