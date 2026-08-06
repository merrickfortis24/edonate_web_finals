@component('layouts.donor-portal', [
    'pageTitle' => 'Alerts',
    'pageHeading' => 'Alerts',
    'pageSubheading' => 'Stay updated with appointment and eligibility notifications.',
    'navLinks' => $navLinks,
    'activeNav' => $activeNav,
    'user' => $user,
    'totalDonations' => $totalDonations,
])
<x-dashboard.card title="Notifications" subtitle="Latest alerts for your donor account.">
    @if ($alerts->isEmpty())
        <p class="text-sm text-slate-600">No alerts available right now.</p>
    @else
        <div class="space-y-3">
            @foreach ($alerts as $alert)
                <article class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ ucfirst(str_replace('_', ' ', $alert->notification_type ?? 'general')) }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $alert->message }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ (int) ($alert->is_read ?? 0) === 1 ? 'bg-slate-100 text-slate-600' : 'bg-red-50 text-red-700' }}">
                            {{ (int) ($alert->is_read ?? 0) === 1 ? 'Read' : 'New' }}
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">{{ $alert->created_at ? \Carbon\Carbon::parse($alert->created_at)->format('M j, Y g:i A') : 'Date unavailable' }}</p>
                </article>
            @endforeach
        </div>
    @endif
</x-dashboard.card>
@endcomponent