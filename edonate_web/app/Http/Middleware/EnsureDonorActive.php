<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureDonorActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $donorId = (int) $request->session()->get('donor_id');

        if ($donorId <= 0 || ! Schema::hasTable('donors') || ! Schema::hasColumn('donors', 'is_active')) {
            return $next($request);
        }

        $columns = ['is_active'];
        if (Schema::hasColumn('donors', 'birthdate')) {
            $columns[] = 'birthdate';
        }

        $donor = DB::table('donors')
            ->where('donor_id', $donorId)
            ->first($columns);

        if ($donor === null) {
            return $next($request);
        }

        $isActive = $donor->is_active === null || (bool) $donor->is_active;
        $isKnownUnderage = $this->isKnownUnderage($donor->birthdate ?? null);

        if ($isActive && ! $isKnownUnderage) {
            return $next($request);
        }

        $message = $isKnownUnderage
            ? 'This donor account does not meet the minimum age requirement.'
            : 'This donor account is inactive. Please contact the donation center.';

        $request->session()->forget([
            'donor_auth_id',
            'donor_id',
            'donor_email',
            'donor_name',
            'auth_provider',
            'terms_accepted',
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        return redirect()
            ->route('donor.login')
            ->with('error', $message);
    }

    private function isKnownUnderage(mixed $birthdate): bool
    {
        if ($birthdate === null || trim((string) $birthdate) === '') {
            return false;
        }

        try {
            return Carbon::parse((string) $birthdate)
                ->gt(Carbon::today()->subYears((int) config('privacy.minimum_age', 18)));
        } catch (Throwable) {
            return true;
        }
    }
}
