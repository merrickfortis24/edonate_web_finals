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
    @if ($alerts->isNotEmpty())
        <div class="mb-4 flex justify-end">
            <form method="POST" action="{{ route('donor.notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-50">
                    Mark all as read
                </button>
            </form>
        </div>
    @endif
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
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ (int) ($alert->is_read ?? 0) === 1 ? 'bg-slate-100 text-slate-600' : 'bg-red-50 text-red-700' }}">
                                {{ (int) ($alert->is_read ?? 0) === 1 ? 'Read' : 'New' }}
                            </span>
                            @if ((int) ($alert->is_read ?? 0) !== 1)
                                <form method="POST" action="{{ route('donor.notifications.read', ['notification' => $alert->notification_id]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Mark read</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">{{ $alert->created_at ? \Carbon\Carbon::parse($alert->created_at)->format('M j, Y g:i A') : 'Date unavailable' }}</p>
                </article>
            @endforeach
        </div>
    @endif
</x-dashboard.card>
@endcomponent
