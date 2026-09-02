<?php

namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;

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
}
