@component('layouts.donor-portal', [
    'pageTitle' => 'Book Appointment',
    'pageHeading' => 'Book Appointment',
    'pageSubheading' => 'Reserve your preferred date and review your upcoming schedules.',
    'navLinks' => $navLinks,
    'activeNav' => $activeNav,
    'user' => $user,
    'totalDonations' => $totalDonations,
])
<x-dashboard.card title="Appointment Booking" subtitle="Booking form placeholder wired to Laravel route.">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700" for="appointment_date">Preferred Date</label>
            <input id="appointment_date" type="date" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200">
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700" for="appointment_time">Preferred Time</label>
            <input id="appointment_time" type="time" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200">
        </div>
    </div>
    <button type="button" class="mt-4 rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-800">
        Submit Booking (placeholder)
    </button>
</x-dashboard.card>

<x-dashboard.card class="mt-6" title="Upcoming Appointments" subtitle="Your scheduled and pending appointments.">
    @if ($appointments->isEmpty())
        <p class="text-sm text-slate-600">No appointments found. Your future schedules will appear here.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead>
                <tr class="border-b border-slate-200 text-slate-500">
                    <th class="px-3 py-2 font-semibold">Date</th>
                    <th class="px-3 py-2 font-semibold">Time</th>
                    <th class="px-3 py-2 font-semibold">Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($appointments as $appointment)
                    <tr class="border-b border-slate-100 last:border-none">
                        <td class="px-3 py-3 font-semibold text-slate-900">
                            {{ $appointment->appointment_date ? \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y') : '-' }}
                        </td>
                        <td class="px-3 py-3 text-slate-700">
                            {{ $appointment->appointment_time ? \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A') : '-' }}
                        </td>
                        <td class="px-3 py-3">
                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">{{ ucfirst($appointment->status ?? 'pending') }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-dashboard.card>
@endcomponent