<?php

namespace App\Http\Controllers;

use App\Services\PrivacyConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrivacyController extends Controller
{
    public function update(Request $request, PrivacyConsent $consent): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', Rule::in([config('privacy.version')])],
            'analytics' => ['required', 'boolean'],
            'maps' => ['required', 'boolean'],
            'ai' => ['required', 'boolean'],
        ]);
        $choices = [
            'analytics' => (bool) $data['analytics'] && (bool) config('privacy.analytics_enabled'),
            'maps' => (bool) $data['maps'],
            'ai' => (bool) $data['ai'] && (bool) config('privacy.ai_enabled'),
        ];
        if (in_array(true, $choices, true) && ! $consent->readyForCollection()) {
            return response()->json(['message' => 'Optional services are disabled until the operator publishes the reviewed privacy notice.'], 503);
        }
        // Persist before activating anything optional; failed writes remain denied.
        $consent->record($request, 'optional-services', $choices);
        $value = $choices + [
            'version' => config('privacy.version'),
            'expires_at' => now()->addDays(config('privacy.choice_days'))->timestamp,
        ];

        return response()->json(['choices' => $choices + [
            'decided' => true, 'necessary' => true, 'expires_at' => $value['expires_at'],
        ]])->header('Cache-Control', 'no-store, private')->cookie(
            config('privacy.cookie'), json_encode($value, JSON_THROW_ON_ERROR),
            config('privacy.choice_days') * 24 * 60, '/', null,
            $request->isSecure() || app()->isProduction(), true, false, 'lax'
        );
    }
}
