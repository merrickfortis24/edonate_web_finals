@component('layouts.donor-portal', [
    'pageTitle' => 'Donation History',
    'pageHeading' => 'Donation History',
    'pageSubheading' => 'Track your donation milestones and contribution records.',
    'navLinks' => $navLinks,
    'activeNav' => $activeNav,
    'user' => $user,
    'totalDonations' => $totalDonations,
])
<x-dashboard.card title="Past Donations" subtitle="Most recent donation records for your account.">
    @if ($donationHistory->isEmpty())
        <p class="text-sm text-slate-600">No donation records yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead>
                <tr class="border-b border-slate-200 text-slate-500">
                    <th class="px-3 py-2 font-semibold">Donation Date</th>
                    <th class="px-3 py-2 font-semibold">Units</th>
                    <th class="px-3 py-2 font-semibold">Remarks</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($donationHistory as $record)
                    <tr class="border-b border-slate-100 last:border-none">
                        <td class="px-3 py-3 font-semibold text-slate-900">
                            {{ $record->donation_date ? \Carbon\Carbon::parse($record->donation_date)->format('F j, Y') : '-' }}
                        </td>
                        <td class="px-3 py-3 text-slate-700">{{ $record->blood_units ?? '-' }}</td>
                        <td class="px-3 py-3 text-slate-700">{{ $record->remarks ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-dashboard.card>
@endcomponent