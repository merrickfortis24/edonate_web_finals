<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin-mfa.{challenge}', function ($request, string $challenge): bool {
    $pending = $request->session()->get('pending_admin_2fa');
    if (! is_array($pending)) {
        return false;
    }

    $expected = trim((string) ($pending['challenge_id'] ?? ''));

    return $expected !== '' && hash_equals($expected, trim($challenge));
});
