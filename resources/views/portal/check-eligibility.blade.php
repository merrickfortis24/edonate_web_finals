@component('layouts.donor-portal', [
    'pageTitle' => 'Check Eligibility',
    'pageHeading' => 'Eligibility Status',
    'pageSubheading' => 'Review your current eligibility status and next donation timeline.',
    'navLinks' => $navLinks,
    'activeNav' => $activeNav,
    'user' => $user,
    'totalDonations' => $totalDonations,
])
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <x-dashboard.card title="Current Eligibility" subtitle="Calculated from latest status records.">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Status</p>
                    <p class="mt-1 text-xl font-bold text-red-700">{{ ucfirst($latestEligibility?->status ?? 'Pending review') }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Next Eligible Date</p>
                    <p class="mt-1 text-xl font-bold text-red-700">{{ $nextEligibleDate ? \Carbon\Carbon::parse($nextEligibleDate)->format('F j, Y') : 'To be determined' }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 sm:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Last Donation Date</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $latestDonationDate ? \Carbon\Carbon::parse($latestDonationDate)->format('F j, Y') : 'No donation records yet' }}</p>
                </div>
            </div>
        </x-dashboard.card>
    </div>
    <div class="lg:col-span-1">
        <x-dashboard.card title="Action" subtitle="Run a fresh eligibility check after new donation records.">
            <button type="button" class="w-full rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-800">
                Recheck Eligibility (placeholder)
            </button>
        </x-dashboard.card>
    </div>
</div>
@endcomponent