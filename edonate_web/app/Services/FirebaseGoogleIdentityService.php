<?php

namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class FirebaseGoogleIdentityService
{
    /**
     * Verify a Firebase ID token and return only the identity claims needed by
     * the application. The client-provided email, UID, and display name are
     * deliberately not used for authentication.
     *
     * @return array{uid:string,email:string,name:string,email_verified:bool,provider:string}
     */
    public function verify(string $idToken): array
    {
        $idToken = trim($idToken);
        if ($idToken === '') {
            throw new InvalidArgumentException('The Firebase ID token is required.');
        }

        $verifiedToken = app('firebase.auth')->verifyIdToken($idToken);
        $claims = $verifiedToken->claims();

        $uid = trim((string) $claims->get('sub', ''));
        $email = Str::lower(trim((string) $claims->get('email', '')));
        $firebaseClaim = $claims->get('firebase', []);
        $provider = is_array($firebaseClaim)
            ? trim((string) ($firebaseClaim['sign_in_provider'] ?? ''))
            : '';

        if ($uid === '' || $email === '') {
            throw new InvalidArgumentException('The Firebase token does not contain a usable identity.');
        }

        return [
            'uid' => $uid,
            'email' => $email,
            'name' => trim((string) $claims->get('name', '')),
            'email_verified' => (bool) $claims->get('email_verified', false),
            'provider' => $provider,
        ];
    }

    /**
     * Convert SDK failures into safe diagnostic categories. Exception text is
     * inspected only in memory because it can contain a JWT prefix.
     */
    public static function classifyVerificationFailure(Throwable $exception): string
    {
        $messages = [];
        $current = $exception;

        do {
            $messages[] = Str::lower($current->getMessage());
            $current = $current->getPrevious();
        } while ($current !== null && count($messages) < 5);

        $details = implode(' ', $messages);

        if (Str::contains($details, ['token is expired', 'token has expired', 'expired id token'])) {
            return 'expired_firebase_token';
        }

        if (Str::contains($details, [
            'not allowed to be used by this audience',
            'not issued by the given issuers',
            'different firebase project',
            'frontend and backend project configuration do not match',
        ])) {
            return 'firebase_project_mismatch';
        }

        if (Str::contains($details, [
            'fetchgooglepublickeys',
            'failed in fetching keys',
            'no keys are available',
            'www.googleapis.com/robot/v1/metadata/x509',
        ])) {
            return 'firebase_public_keys_unavailable';
        }

        if (Str::contains($details, ['service-account', 'credentials file', 'without a project id'])) {
            return 'firebase_configuration_error';
        }

        if (Str::contains($details, ['token has been revoked', 'revoked id token'])) {
            return 'revoked_firebase_token';
        }

        if (Str::contains($details, [
            'jwt string',
            'not a verified id token',
            'token is invalid',
            'signature',
            'key id',
            'could not be parsed',
        ])) {
            return 'invalid_firebase_token';
        }

        return 'firebase_token_verification_failed';
    }
}
