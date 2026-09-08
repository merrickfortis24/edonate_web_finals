@component('layouts.donor-portal', [
    'pageTitle' => 'Book Appointment',
    'pageHeading' => 'Book Appointment',
    'pageSubheading' => 'Choose an available donation event and confirm your slot.',
    'navLinks' => $navLinks,
    'activeNav' => $activeNav,
    'user' => $user,
    'totalDonations' => $totalDonations,
])
@php
    $bookingAllowed = $canBookAppointment ?? false;
    $verificationStatusLabel = \Illuminate\Support\Str::headline(str_replace('_', ' ', $identityVerificationStatus ?? 'unverified'));
@endphp

<section class="mx-auto w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-slate-200/70">
    <header class="flex min-h-16 items-center justify-between bg-red-800 px-4 py-3 text-white sm:px-6">
        <a href="{{ route('donor.dashboard') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/40 text-xl leading-none transition hover:bg-white/10" aria-label="Back to dashboard">
            &larr;
        </a>
        <h2 class="text-center text-lg font-extrabold sm:text-xl">Book Appointment</h2>
        <span class="w-9" aria-hidden="true"></span>
    </header>

    <form method="POST" action="{{ route('donor.book-appointment.store') }}" class="space-y-6 p-4 sm:p-6 lg:p-8">
        @csrf
        <x-privacy-acknowledgment purpose="appointment" />

        @if (! $bookingAllowed)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                Identity verification and eligibility clearance are required before booking.
                Current verification status: <span class="font-semibold">{{ $verificationStatusLabel }}</span>.
                <a href="{{ route('donor.verification.index') }}" class="font-semibold underline">Open verification</a>
            </div>

            @foreach (($bookingReadiness['messages'] ?? []) as $message)
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $message }}</p>
            @endforeach
        @endif

        <div>
            <h3 class="text-base font-bold text-red-800 sm:text-lg">Available Donation Events</h3>
            <p class="mt-1 text-sm text-slate-500">Your appointment date and donation center are copied from the selected event.</p>
        </div>

        @error('event_id')
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror
        @error('appointment_time')
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror

        @if ($eventOptions->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-600">
                No donation events are open for booking right now.
            </div>
        @else
            <div class="grid gap-4">
                @foreach ($eventOptions as $event)
                    @php
                        $eventId = (int) $event['event_id'];
                        $selected = (string) old('event_id') === (string) $eventId || ($loop->first && ! old('event_id'));
                    @endphp
                    <label class="block rounded-xl border border-slate-200 p-4 shadow-sm transition has-[:checked]:border-red-700 has-[:checked]:ring-2 has-[:checked]:ring-red-100">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div class="flex gap-3">
                                <input
                                    type="radio"
                                    name="event_id"
                                    required
                                    value="{{ $eventId }}"
                                    class="mt-1 h-4 w-4 border-slate-300 text-red-700 focus:ring-red-500"
                                    data-start-time="{{ substr((string) ($event['start_time'] ?? ''), 0, 5) }}"
                                    data-end-time="{{ substr((string) ($event['end_time'] ?? ''), 0, 5) }}"
                                    @checked($selected)
                                    {{ $bookingAllowed ? '' : 'disabled' }}
                                >
                                <div>
                                    <p class="text-base font-bold text-slate-900">{{ $event['title'] }}</p>
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{ \Carbon\Carbon::parse($event['event_date'])->format('F j, Y') }}
                                        @if ($event['start_time'])
                                            at {{ \Carbon\Carbon::parse($event['start_time'])->format('g:i A') }}
                                        @endif
                                        @if ($event['end_time'])
                                            - {{ \Carbon\Carbon::parse($event['end_time'])->format('g:i A') }}
                                        @endif
                                    </p>
                                    <p class="mt-1 text-sm text-slate-600">{{ $event['location_name'] }}</p>
                                    @if (! empty($event['address']))
                                        <p class="mt-1 text-xs text-slate-500">{{ $event['address'] }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                                {{ number_format($event['remaining_slots']) }} slots left
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            <div>
                <label for="appointment_time" class="mb-1.5 block text-sm font-semibold text-slate-700">Appointment Time</label>
                <select id="appointment_time" name="appointment_time" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" {{ $bookingAllowed ? '' : 'disabled' }}></select>
            </div>

            <div class="pt-1">
                <button type="submit" class="w-full rounded-xl bg-red-800 px-4 py-3 text-sm font-semibold text-white transition hover:bg-red-900 disabled:cursor-not-allowed disabled:bg-slate-400 sm:mx-auto sm:block sm:w-auto sm:min-w-60" {{ $bookingAllowed ? '' : 'disabled' }}>
                    Confirm Appointment
                </button>
            </div>
        @endif
    </form>
</section>

<x-dashboard.card class="mx-auto mt-6 w-full max-w-5xl" title="Upcoming Appointments" subtitle="Your confirmed and active schedules.">
    @if ($appointments->isEmpty())
        <p class="text-sm text-slate-600">No appointments found. Your future schedules will appear here.</p>
    @else
        <div class="overflow-x-auto" role="region" aria-label="Upcoming appointments" tabindex="0">
            <table class="min-w-full text-left text-sm">
                <thead>
                <tr class="border-b border-slate-200 text-slate-500">
                    <th class="px-3 py-2 font-semibold">Event</th>
                    <th class="px-3 py-2 font-semibold">Date</th>
                    <th class="px-3 py-2 font-semibold">Time</th>
                    <th class="px-3 py-2 font-semibold">Status</th>
                    <th class="px-3 py-2 font-semibold">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($appointments as $appointment)
                    @php
                        $normalizedStatus = app(\App\Services\AppointmentBookingService::class)->normalizeAppointmentStatus((string) $appointment->status);
                        $canCancel = ! in_array($normalizedStatus, ['cancelled', 'completed', 'no_show'], true)
                            && $appointment->appointment_date
                            && \Carbon\Carbon::parse($appointment->appointment_date)->gte(\Carbon\Carbon::today());
                    @endphp
                    <tr class="border-b border-slate-100 last:border-none">
                        <td class="px-3 py-3 font-semibold text-slate-900">{{ $appointment->event?->title ?? 'Legacy appointment' }}</td>
                        <td class="px-3 py-3 text-slate-700">{{ $appointment->appointment_date ? \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y') : '-' }}</td>
                        <td class="px-3 py-3 text-slate-700">{{ $appointment->appointment_time ? \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A') : '-' }}</td>
                        <td class="px-3 py-3">
                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">{{ \Illuminate\Support\Str::headline($normalizedStatus) }}</span>
                        </td>
                        <td class="px-3 py-3 text-right">
                            @if ($canCancel)
                                <form method="POST" action="{{ route('donor.appointments.cancel', $appointment->appointment_id) }}" onsubmit="return confirm('Cancel this appointment?');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-dashboard.card>

<script>
    (function () {
        var select = document.getElementById('appointment_time');
        var radios = Array.prototype.slice.call(document.querySelectorAll('input[name="event_id"]'));
        var oldTime = @json(old('appointment_time'));

        function toMinutes(value) {
            var parts = String(value || '').split(':');
            return (Number(parts[0] || 0) * 60) + Number(parts[1] || 0);
        }

        function timeLabel(value) {
            var date = new Date('1970-01-01T' + value + ':00');
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        }

        function fillTimes(radio) {
            if (!select || !radio) return;

            var start = radio.dataset.startTime || '';
            var end = radio.dataset.endTime || '';
            var startMinutes = toMinutes(start);
            var endMinutes = toMinutes(end || start);
            var options = [];

            for (var minute = startMinutes; minute <= endMinutes; minute += 30) {
                var hour = String(Math.floor(minute / 60)).padStart(2, '0');
                var min = String(minute % 60).padStart(2, '0');
                var value = hour + ':' + min;
                options.push('<option value="' + value + '"' + (oldTime === value ? ' selected' : '') + '>' + timeLabel(value) + '</option>');
            }

            select.innerHTML = options.join('');
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                fillTimes(radio);
            });
        });

        fillTimes(radios.find(function (radio) { return radio.checked; }) || radios[0]);
    })();
</script>
@endcomponent
