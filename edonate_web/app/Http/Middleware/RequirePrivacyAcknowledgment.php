<?php

namespace App\Http\Middleware;

use App\Services\PrivacyConsent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class RequirePrivacyAcknowledgment
{
    public function handle(Request $request, Closure $next): Response
    {
        $purpose = config('privacy.data_forms')[$request->route()?->getName()] ?? null;
        if ($purpose === null || $request->isMethodSafe()) {
            return $next($request);
        }
        if (! app(PrivacyConsent::class)->readyForCollection()) {
            abort(503, 'Personal-data submissions are temporarily disabled while the operator completes the privacy notice.');
        }

        $request->validate([
            'privacy_version' => ['required', Rule::in([config('privacy.version')])],
            'privacy_acknowledged' => ['required', 'accepted'],
            'purpose_accepted' => ['required', 'accepted'],
        ], [
            'privacy_version.*' => 'The privacy notice has changed. Refresh the form and review it before submitting.',
            'privacy_acknowledged.*' => 'Read the Terms and Privacy Policy and tick the acknowledgment to continue.',
            'purpose_accepted.*' => 'Please review and accept the specific purpose of this submission.',
        ]);

        // Record the affirmative action before any personal data or email is sent.
        app(PrivacyConsent::class)->record($request, $purpose, [
            'terms' => true, 'privacy_notice' => true, 'purpose' => true,
        ]);

        return $next($request);
    }
}
