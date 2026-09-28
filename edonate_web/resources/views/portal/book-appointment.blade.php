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
    $donorRestricted = isset($donor) && (bool) ($donor->appointment_restricted ?? false);
    $consecutiveCancellationCount = $consecutiveCancellationCount ?? (int) ($donor->consecutive_cancellations ?? 0);
    $activeRestriction = $activeRestriction ?? null;
    $pendingRestrictionAppeal = $pendingRestrictionAppeal ?? null;
@endphp

<section class="mx-auto w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-slate-200/70">
    <header class="flex min-h-16 items-center justify-between bg-red-800 px-4 py-3 text-white sm:px-6">
        <a href="{{ route('donor.dashboard') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/40 text-xl leading-none transition hover:bg-white/10" aria-label="Back to dashboard">
            &larr;
        </a>
        <h2 class="text-center text-lg font-extrabold sm:text-xl">Book Appointment</h2>
        <span class="w-9" aria-hidden="true"></span>
    </header>

    @if (session('success') || session('error') || session('cancellation_warning') || $errors->any())
        <div class="space-y-2 px-4 pt-4 sm:px-6 lg:px-8" aria-live="polite">
            @if (session('success'))<p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</p>@endif
            @if (session('error'))<p class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error') }}</p>@endif
            @if (session('cancellation_warning'))<p class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">{{ session('cancellation_warning') }}</p>@endif
            @foreach ($errors->all() as $error)<p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $error }}</p>@endforeach
        </div>
    @endif

    @if ($donorRestricted)
        @php
            $contactEmail = config('privacy.contact_email') ?: config('mail.from.address');
        @endphp
        <section class="mx-4 mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-red-900 sm:mx-6 lg:mx-8" aria-labelledby="appointmentRestrictionTitle">
            <h3 id="appointmentRestrictionTitle" class="text-base font-extrabold">Account Temporarily Restricted</h3>
            <p class="mt-2 text-sm">Your appointment privileges have been temporarily restricted because you cancelled three consecutive appointments. To restore your appointment privileges, please contact the eDonate administrator and provide a valid explanation for the cancellations.</p>
            <p class="mt-2 text-sm"><span class="font-semibold">Restriction Date:</span> {{ ($activeRestriction?->restricted_at ?? $donor->restricted_at)?->format('F j, Y g:i A') ?? 'Not available' }}</p>
            @if ($contactEmail)
                <a class="mt-3 inline-flex rounded-lg bg-red-800 px-4 py-2 text-sm font-semibold text-white hover:bg-red-900" href="mailto:{{ $contactEmail }}">Contact Administrator</a>
            @else
                <p class="mt-3 text-sm font-semibold">Please use the administrator contact details provided by your organization.</p>
            @endif

            @if ($pendingRestrictionAppeal)
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="status">
                    Your justification was submitted {{ $pendingRestrictionAppeal->submitted_at?->diffForHumans() ?? 'recently' }} and is pending administrator review.
                </div>
            @else
                <form method="POST" action="{{ route('donor.appointments.restriction-appeal') }}" class="mt-4 space-y-3 rounded-lg border border-red-200 bg-white p-4">
                    @csrf
                    <div>
                        <h4 class="font-bold">Request Account Review</h4>
                        <p class="mt-1 text-sm text-slate-600">Please explain why you cancelled your previous appointments. The administrator will review your request before restoring your appointment privileges.</p>
                    </div>
                    <label for="restrictionJustification" class="block text-sm font-semibold text-slate-700">Reason / Justification</label>
                    <textarea id="restrictionJustification" name="justification" rows="4" minlength="20" maxlength="2000" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" placeholder="Explain the circumstances around your cancellations (at least 20 characters).">{{ old('justification') }}</textarea>
                    @error('justification')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-xl bg-red-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-900">Submit for Review</button>
                </form>
            @endif
        </section>
    @endif

    <form method="POST" action="{{ route('donor.book-appointment.store') }}" class="space-y-6 p-4 sm:p-6 lg:p-8">
        @csrf
        <x-privacy-acknowledgment purpose="appointment" />

        @if (! $bookingAllowed && ! $donorRestricted)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                You can still view upcoming events, but booking is disabled until your account, identity, and eligibility requirements are satisfied for an event.
                Current verification status: <span class="font-semibold">{{ $verificationStatusLabel }}</span>.
                <a href="{{ route('donor.verification.index') }}" class="font-semibold underline">Open verification</a>
            </div>

            @foreach (($bookingReadiness['messages'] ?? []) as $message)
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $message }}</p>
            @endforeach
        @endif

        <div>
            <h3 class="text-base font-bold text-red-800 sm:text-lg">Upcoming Donation Events</h3>
            <p class="mt-1 text-sm text-slate-500">Open, full, and closed events remain visible; booking is enabled only when the selected event and your eligibility allow it.</p>
        </div>

        @error('event_id')
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror
        @error('appointment_time')
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror

        @if ($eventOptions->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-600">
                No upcoming donation events are currently posted.
            </div>
        @else
            <div class="grid gap-4">
                @foreach ($eventOptions as $event)
                    @php
                        $eventId = (int) $event['event_id'];
                        $eventStatus = $event['status'] ?? 'open';
                        $eventCanBook = (bool) ($event['booking_allowed'] ?? $bookingAllowed);
                        $preferredEventId = (int) old('event_id', $selectedEventId ?? 0);
                        $selected = $preferredEventId > 0
                            ? $preferredEventId === $eventId
                            : ($loop->first && $eventCanBook);
                    @endphp
                    <label class="block rounded-xl border border-slate-200 p-4 shadow-sm transition has-[:checked]:border-red-700 has-[:checked]:ring-2 has-[:checked]:ring-red-100 {{ $eventCanBook ? 'bg-white' : 'bg-slate-50' }}">
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
                                    {{ $eventCanBook ? '' : 'disabled' }}
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
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ \Illuminate\Support\Str::headline($eventStatus) }}</p>
                                    @if (! $eventCanBook && ! empty($event['booking_reason']))
                                        <p class="mt-2 text-sm text-amber-800" role="status">{{ $event['booking_reason'] }}</p>
                                        @if (! empty($event['next_eligible_date']) && str_contains(strtolower($event['booking_reason']), 'eligib'))
                                            <p class="mt-1 text-sm text-slate-600">Next eligible date: <span class="font-semibold">{{ \Carbon\Carbon::parse($event['next_eligible_date'])->format('F j, Y') }}</span></p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                                @if ($eventStatus !== 'open')
                                    {{ \Illuminate\Support\Str::headline($eventStatus) }}
                                @elseif (($event['availability_status'] ?? null) === 'full')
                                    Full
                                @else
                                    {{ number_format($event['remaining_slots']) }} slots left
                                @endif
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
                                <button
                                    type="button"
                                    data-open-cancellation
                                    data-appointment-id="{{ $appointment->appointment_id }}"
                                    data-event-name="{{ $appointment->event?->title ?? 'Legacy appointment' }}"
                                    data-appointment-date="{{ $appointment->appointment_date ? \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y') : 'Date unavailable' }}"
                                    data-appointment-time="{{ $appointment->appointment_time ? \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A') : 'Time unavailable' }}"
                                    class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                >Cancel</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-dashboard.card>

<dialog id="cancelAppointmentDialog" class="w-[calc(100%-2rem)] max-w-xl rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-900/60">
    <form id="cancelAppointmentForm" method="POST" action="{{ route('donor.appointments.cancel', ['appointment' => '__APPOINTMENT__']) }}" class="overflow-hidden rounded-2xl bg-white">
        @csrf
        @method('PATCH')
        <div class="bg-red-800 px-5 py-4 text-white">
            <h2 class="text-lg font-bold">Cancel Appointment?</h2>
            <p id="cancelAppointmentSummary" class="mt-1 text-sm text-red-100"></p>
        </div>
        <div class="space-y-4 p-5">
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-bold">Please provide your reason for cancelling this appointment.</p>
                <p class="mt-1">Repeated appointment cancellations may result in temporary restriction of your appointment privileges. Three consecutive cancellations will require administrator review before you can book another appointment.</p>
                @if ($consecutiveCancellationCount === 1)
                    <p class="mt-2 font-semibold">You have already cancelled 1 consecutive appointment; 2 more may restrict booking.</p>
                @elseif ($consecutiveCancellationCount >= 2)
                    <p class="mt-2 font-semibold">Warning: you have cancelled {{ $consecutiveCancellationCount }} consecutive appointments. One more will temporarily restrict your appointment privileges.</p>
                @endif
            </div>
            <label for="cancellationReason" class="block text-sm font-semibold text-slate-700">Reason for cancellation <span class="text-red-600">*</span></label>
            <textarea id="cancellationReason" name="cancellation_reason" rows="4" maxlength="1000" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" placeholder="Please explain why you need to cancel."></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" data-close-cancellation class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Keep Appointment</button>
                <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Confirm Cancellation</button>
            </div>
        </div>
    </form>
</dialog>

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

    (function () {
        var dialog = document.getElementById('cancelAppointmentDialog');
        var form = document.getElementById('cancelAppointmentForm');
        var summary = document.getElementById('cancelAppointmentSummary');
        var reason = document.getElementById('cancellationReason');
        if (!dialog || !form) return;
        var actionTemplate = form.action;

        document.querySelectorAll('[data-open-cancellation]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = button.dataset.appointmentId || '';
                form.action = actionTemplate.replace('__APPOINTMENT__', encodeURIComponent(id));
                summary.textContent = (button.dataset.eventName || 'Appointment') + ' · ' + (button.dataset.appointmentDate || '') + ' · ' + (button.dataset.appointmentTime || '');
                reason.value = '';
                if (typeof dialog.showModal === 'function') dialog.showModal();
            });
        });

        document.querySelectorAll('[data-close-cancellation]').forEach(function (button) {
            button.addEventListener('click', function () { dialog.close(); });
        });

        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) dialog.close();
        });
    })();
</script>
@endcomponent
