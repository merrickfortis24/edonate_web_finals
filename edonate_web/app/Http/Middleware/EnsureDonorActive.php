<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureDonorActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $donorId = (int) $request->session()->get('donor_id');

        if ($donorId <= 0 || ! Schema::hasTable('donors') || ! Schema::hasColumn('donors', 'is_active')) {
            return $next($request);
        }

        $isActive = DB::table('donors')
            ->where('donor_id', $donorId)
            ->value('is_active');

        if ($isActive === null || (bool) $isActive) {
            return $next($request);
        }

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
                'message' => 'This donor account is inactive. Please contact the donation center.',
            ], 403);
        }

        return redirect()
            ->route('donor.login')
            ->with('error', 'This donor account is inactive. Please contact the donation center.');
    }
}
