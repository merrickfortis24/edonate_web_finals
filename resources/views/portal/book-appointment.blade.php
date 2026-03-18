@component('layouts.donor-portal', [
    'pageTitle' => 'Book Appointment',
    'pageHeading' => 'Book Appointment',
    'pageSubheading' => 'Reserve your preferred date and review your upcoming schedules.',
    'navLinks' => $navLinks,
    'activeNav' => $activeNav,
    'user' => $user,
    'totalDonations' => $totalDonations,
])
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .booking-calendar-shell .flatpickr-calendar {
        width: 100%;
        max-width: 100%;
        box-shadow: none;
        border: 1px solid #d9d9d9;
        background: #e6e6e6;
        border-radius: 16px;
        padding: 10px;
    }

    .booking-calendar-shell .flatpickr-rContainer,
    .booking-calendar-shell .flatpickr-days,
    .booking-calendar-shell .dayContainer {
        width: 100%;
        min-width: 100%;
        max-width: 100%;
    }

    .booking-calendar-shell .flatpickr-day {
        border-radius: 10px;
        border: 0;
        height: 40px;
        line-height: 40px;
        max-width: 14.2857%;
    }

    .booking-calendar-shell .flatpickr-day.selected,
    .booking-calendar-shell .flatpickr-day.startRange,
    .booking-calendar-shell .flatpickr-day.endRange {
        background: #2c2c2c;
        border-color: #2c2c2c;
        color: #f5f5f5;
    }

    .booking-calendar-shell .flatpickr-day.flatpickr-disabled,
    .booking-calendar-shell .flatpickr-day.notAllowed {
        color: #b3b3b3;
    }

    .booking-calendar-shell .flatpickr-day.booking-available {
        box-shadow: inset 0 0 0 1px rgba(9, 141, 0, 0.35);
        background: #f5fff5;
    }

    .booking-calendar-shell .flatpickr-day.booking-full {
        box-shadow: inset 0 0 0 1px rgba(133, 0, 0, 0.2);
        background: #f2f2f2;
    }

    .booking-calendar-shell .flatpickr-months {
        margin-bottom: 6px;
    }

    .booking-calendar-shell .flatpickr-current-month {
        font-size: 14px;
    }

    .booking-calendar-shell .flatpickr-weekday {
        color: #757575;
        font-weight: 500;
    }

    @media (min-width: 640px) {
        .booking-calendar-shell .flatpickr-day {
            height: 44px;
            line-height: 44px;
        }
    }
</style>

<section class="mx-auto w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-slate-200/70">
    <header class="flex min-h-16 items-center justify-between bg-red-800 px-4 py-3 text-white sm:px-6">
        <a
            href="{{ route('donor.dashboard') }}"
            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/40 text-xl leading-none transition hover:bg-white/10"
            aria-label="Back to dashboard"
        >
            &larr;
        </a>
        <h2 class="text-center text-lg font-extrabold sm:text-xl">Book Appointment</h2>
        <span class="w-9" aria-hidden="true"></span>
    </header>

    <form method="POST" action="{{ route('donor.book-appointment.store') }}" class="space-y-6 p-4 sm:p-6 lg:p-8">
        @csrf

        <div>
            <h3 class="text-base font-bold text-red-800 sm:text-lg">Schedule Your Donation</h3>
            <p class="mt-1 text-sm text-slate-500">Select your preferred date, time, and location for blood donation.</p>
        </div>

        <section aria-labelledby="calendar-label" class="space-y-3">
            <p id="calendar-label" class="text-sm font-semibold text-slate-600">Select Date</p>

            <div class="booking-calendar-shell">
                <input
                    type="text"
                    id="donation_date"
                    name="donation_date"
                    value="{{ old('donation_date') }}"
                    placeholder="Select Date"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"
                    readonly
                >
            </div>
            @error('donation_date')
                <p class="text-xs font-medium text-red-600">{{ $message }}</p>
            @enderror
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="donation_center" class="mb-1.5 block text-sm font-semibold text-slate-700">Donation Center</label>
                <select id="donation_center" name="donation_center" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200">
                    <option disabled {{ old('donation_center') ? '' : 'selected' }}>Choose Donation Center</option>
                    <option>{{ $location?->city ? $location->city . ' Blood Bank' : 'City Blood Bank' }}</option>
                    <option>Central Health Donor Hub</option>
                    <option>Red Cross Donation Center</option>
                </select>
                @error('donation_center')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="time_slot" class="mb-1.5 block text-sm font-semibold text-slate-700">Time Slot</label>
                <select id="time_slot" name="time_slot" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" {{ old('donation_date') ? '' : 'disabled' }}>
                    <option disabled {{ old('time_slot') ? '' : 'selected' }}>Select date first</option>
                    @foreach ($timeSlots as $slot)
                        <option value="{{ $slot }}" {{ old('time_slot') === $slot ? 'selected' : '' }}>{{ \Carbon\Carbon::createFromFormat('H:i', $slot)->format('g:i A') }}</option>
                    @endforeach
                </select>
                @error('time_slot')
                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <div class="pt-1">
            <button type="submit" class="w-full rounded-xl bg-red-800 px-4 py-3 text-sm font-semibold text-white transition hover:bg-red-900 sm:mx-auto sm:block sm:w-auto sm:min-w-60">
                Confirm Booking
            </button>
        </div>
    </form>
</section>

<x-dashboard.card class="mx-auto mt-6 w-full max-w-4xl" title="Upcoming Appointments" subtitle="Your scheduled and pending appointments.">
    @if ($appointments->isEmpty())
        <p class="text-sm text-slate-600">No appointments found. Your future schedules will appear here.</p>
    @else
        <div class="space-y-3 lg:hidden">
            @foreach ($appointments as $appointment)
                <article class="rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-bold text-slate-900">
                            {{ $appointment->appointment_date ? \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y') : '-' }}
                        </p>
                        <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">{{ ucfirst($appointment->status ?? 'pending') }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600"><span class="font-semibold text-slate-800">Time:</span> {{ $appointment->appointment_time ? \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A') : '-' }}</p>
                </article>
            @endforeach
        </div>

        <div class="hidden overflow-x-auto lg:block">
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

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    (function () {
        const enabledDates = @json($availableDates ?? []);
        const fullyBookedDates = @json($fullyBookedDates ?? []);
        const timeSlotSelect = document.getElementById('time_slot');

        flatpickr('#donation_date', {
            inline: true,
            minDate: 'today',
            dateFormat: 'Y-m-d',
            defaultDate: '{{ old('donation_date') }}' || null,
            enable: enabledDates,
            disable: fullyBookedDates,
            onChange: function (selectedDates, dateStr) {
                if (!timeSlotSelect) return;

                if (dateStr) {
                    timeSlotSelect.disabled = false;
                    if (timeSlotSelect.options.length > 0 && timeSlotSelect.options[0].text.includes('Select date first')) {
                        timeSlotSelect.options[0].text = 'Choose Time Slot';
                    }
                }
            },
            onDayCreate: function (_dObj, _dStr, _fp, dayElem) {
                const dateKey = dayElem.dateObj.toISOString().slice(0, 10);

                if (enabledDates.includes(dateKey)) {
                    dayElem.classList.add('booking-available');
                }

                if (fullyBookedDates.includes(dateKey)) {
                    dayElem.classList.add('booking-full');
                }
            }
        });

        if (timeSlotSelect && document.getElementById('donation_date').value) {
            timeSlotSelect.disabled = false;
            if (timeSlotSelect.options.length > 0 && timeSlotSelect.options[0].text.includes('Select date first')) {
                timeSlotSelect.options[0].text = 'Choose Time Slot';
            }
        }
    })();
</script>
@endcomponent