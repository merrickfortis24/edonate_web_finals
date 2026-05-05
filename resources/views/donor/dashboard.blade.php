<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Donor Dashboard | eDonate</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<div class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(220,38,38,0.16),_transparent_44%),radial-gradient(circle_at_top_right,_rgba(248,113,113,0.10),_transparent_34%)]">
    <x-dashboard.nav :links="$navLinks" :current="$activeNav" :userName="$user->first_name" />

    <main class="mx-auto w-full max-w-7xl px-4 pb-8 pt-5 sm:px-6 lg:pl-72 lg:pr-8 lg:pt-8">
        <section class="rounded-2xl bg-gradient-to-r from-red-950 via-red-800 to-red-600 p-5 text-white shadow-xl sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-red-100">Dashboard</p>
                    <h1 class="mt-2 text-2xl font-extrabold leading-tight sm:text-3xl">Welcome Back, {{ trim($user->first_name . ' ' . $user->last_name) }}</h1>
                    <p class="mt-2 text-sm text-red-100 sm:text-[15px]">Track appointments, eligibility, and your life-saving impact in one place.</p>
                </div>
                <a href="{{ route('donor.alerts') }}" class="w-full rounded-xl bg-white/15 px-4 py-3 text-sm backdrop-blur transition hover:bg-white/25 sm:w-auto sm:min-w-52">
                    <p class="font-semibold">Alerts</p>
                    <p class="mt-1 text-red-100">{{ $alertsCount }} unread notifications</p>
                </a>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:max-w-2xl">
                <x-dashboard.stat-card label="Your Blood Type" :value="$user->blood_type" />
                <x-dashboard.stat-card label="Total Donations" :value="$user->total_donations" />
            </div>
        </section>

        @if (session('success'))
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        <section class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <div class="space-y-6 md:col-span-2 xl:col-span-2">
                <x-dashboard.card title="Quick Actions" subtitle="Launch key donor actions quickly.">
                    <div class="grid gap-4 sm:grid-cols-2" id="book-appointment">
                        <a href="{{ route('donor.book-appointment') }}" class="group rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-red-300 hover:bg-red-50">
                            <p class="text-sm font-semibold text-slate-900">Book Appointment</p>
                            <p class="mt-1 text-sm text-slate-500">Schedule your next donation slot.</p>
                            <span class="mt-3 inline-block text-sm font-semibold text-red-700">Open booking</span>
                        </a>
                        <a href="{{ route('donor.verification.index') }}" class="group rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-red-300 hover:bg-red-50">
                            <p class="text-sm font-semibold text-slate-900">Verify Identity</p>
                            <p class="mt-1 text-sm text-slate-500">Submit a valid ID for admin review.</p>
                            <span class="mt-3 inline-block text-sm font-semibold text-red-700">Open verification</span>
                        </a>
                        <a href="{{ route('donor.history') }}" class="group rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-red-300 hover:bg-red-50">
                            <p class="text-sm font-semibold text-slate-900">History</p>
                            <p class="mt-1 text-sm text-slate-500">View your previous donation records.</p>
                            <span class="mt-3 inline-block text-sm font-semibold text-red-700">View history</span>
                        </a>
                    </div>
                </x-dashboard.card>

                <x-dashboard.card title="Upcoming Appointments" subtitle="Your scheduled donation appointments.">
                    @if ($upcomingAppointments->isEmpty())
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
                            <p class="text-base font-semibold text-slate-800">No upcoming appointments yet.</p>
                            <p class="mt-1 text-sm text-slate-500">Book a schedule to keep your donation streak active.</p>
                        </div>
                    @else
                        <div class="space-y-3 lg:hidden">
                            @foreach ($upcomingAppointments as $appointment)
                                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-sm font-bold text-slate-900">
                                            {{ $appointment->appointment_date ? \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y') : '-' }}
                                        </p>
                                        <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                            {{ ucfirst($appointment->status ?? 'pending') }}
                                        </span>
                                    </div>
                                    <div class="mt-2 space-y-1.5 text-sm text-slate-600">
                                        <p><span class="font-semibold text-slate-800">Time:</span> {{ $appointment->appointment_time ? \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A') : '-' }}</p>
                                        <p><span class="font-semibold text-slate-800">Location:</span> {{ $location?->city ? $location->city . ' Blood Bank' : 'City Blood Bank' }}</p>
                                    </div>
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
                                    <th class="px-3 py-2 font-semibold">Location</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($upcomingAppointments as $appointment)
                                    <tr class="border-b border-slate-100 last:border-none">
                                        <td class="px-3 py-3 font-semibold text-slate-900">
                                            {{ $appointment->appointment_date ? \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y') : '-' }}
                                        </td>
                                        <td class="px-3 py-3 text-slate-700">
                                            {{ $appointment->appointment_time ? \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A') : '-' }}
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                                {{ ucfirst($appointment->status ?? 'pending') }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-slate-700">
                                            {{ $location?->city ? $location->city . ' Blood Bank' : 'City Blood Bank' }}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-dashboard.card>

                <x-dashboard.card id="donation-history" title="Profile Summary" subtitle="Account and contact details on file.">
                    <div class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><span class="font-semibold text-slate-900">Email:</span> {{ session('donor_email') }}</div>
                        <div><span class="font-semibold text-slate-900">Phone:</span> {{ $donor->contact_number ?: '-' }}</div>
                        <div><span class="font-semibold text-slate-900">Birthdate:</span> {{ $donor->birthdate ?: '-' }}</div>
                        <div><span class="font-semibold text-slate-900">Gender:</span> {{ $donor->gender ?: '-' }}</div>
                        <div><span class="font-semibold text-slate-900">Street:</span> {{ $location?->street_address ?: '-' }}</div>
                        <div><span class="font-semibold text-slate-900">Barangay:</span> {{ $location?->barangay_name ?: '-' }}</div>
                        <div><span class="font-semibold text-slate-900">City:</span> {{ $location?->city ?: '-' }}</div>
                        <div><span class="font-semibold text-slate-900">Province:</span> {{ $location?->province ?: '-' }}</div>
                    </div>
                </x-dashboard.card>

                @if (!$profileComplete || $errors->any())
                    <x-dashboard.card title="Complete Your Profile" subtitle="Fill in required fields to unlock booking and donor operations.">
                        @if ($errors->any())
                            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                <p class="font-semibold">Please correct the following:</p>
                                <ul class="mt-1 list-inside list-disc space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('donor.profile.complete') }}" class="grid gap-4 sm:grid-cols-2">
                            @csrf
                            <div>
                                <label for="phone" class="mb-1 block text-sm font-semibold text-slate-700">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="{{ old('phone', $donor->contact_number) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" required>
                            </div>
                            <div>
                                <label for="birthdate" class="mb-1 block text-sm font-semibold text-slate-700">Birthdate</label>
                                <input type="date" id="birthdate" name="birthdate" value="{{ old('birthdate', $donor->birthdate) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" required>
                            </div>
                            <div>
                                <label for="gender" class="mb-1 block text-sm font-semibold text-slate-700">Gender</label>
                                <select id="gender" name="gender" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" required>
                                    <option value="" disabled {{ old('gender', $donor->gender) ? '' : 'selected' }}>Select gender</option>
                                    @foreach (['Male', 'Female', 'Other', 'Prefer not to say'] as $gender)
                                        <option value="{{ $gender }}" {{ old('gender', $donor->gender) === $gender ? 'selected' : '' }}>{{ $gender }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="blood_type" class="mb-1 block text-sm font-semibold text-slate-700">Blood Type</label>
                                <select id="blood_type" name="blood_type" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" required>
                                    <option value="" disabled {{ old('blood_type') ? '' : 'selected' }}>Select blood type</option>
                                    @foreach ($bloodTypes as $type)
                                        <option value="{{ $type }}" {{ old('blood_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="street_address" class="mb-1 block text-sm font-semibold text-slate-700">Street Address</label>
                                <input type="text" id="street_address" name="street_address" value="{{ old('street_address', $location?->street_address) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" required>
                            </div>
                            <div>
                                <label for="barangay" class="mb-1 block text-sm font-semibold text-slate-700">Barangay</label>
                                <input type="text" id="barangay" name="barangay" value="{{ old('barangay', $location?->barangay_name) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" required>
                            </div>
                            <div>
                                <label for="city" class="mb-1 block text-sm font-semibold text-slate-700">Municipality/City</label>
                                <input type="text" id="city" name="city" value="{{ old('city', $location?->city) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" required>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="province" class="mb-1 block text-sm font-semibold text-slate-700">Province</label>
                                <input type="text" id="province" name="province" value="{{ old('province', $location?->province) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200" required>
                            </div>
                            <div class="sm:col-span-2">
                                <button type="submit" class="w-full rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-800">
                                    Save Profile
                                </button>
                            </div>
                        </form>
                    </x-dashboard.card>
                @endif
            </div>

            <div class="space-y-6 md:col-span-2 xl:col-span-1">
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-1">
                    <x-dashboard.card id="check-eligibility" title="Donation Eligibility" subtitle="Latest donor eligibility estimate.">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Next Eligible Date</p>
                            <p class="mt-1 text-lg font-bold text-red-700">{{ $nextEligibleDate }}</p>
                        </div>
                        <a href="{{ route('donor.check-eligibility') }}" class="mt-4 block w-full rounded-xl bg-red-700 px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-red-800">
                            Check Eligibility
                        </a>
                    </x-dashboard.card>

                    <x-dashboard.card title="Your Impact" subtitle="Donation outcomes based on your records.">
                        <div class="rounded-xl bg-gradient-to-r from-red-800 to-red-600 p-5 text-white shadow-md">
                            <p class="text-sm font-semibold">Your {{ $totalDonations }} donations have potentially saved up to {{ $livesImpacted }} lives.</p>
                            <p class="mt-3 rounded-lg bg-white/90 px-3 py-2 text-center text-xs font-semibold text-red-700">
                                Thank you for being a hero in your community.
                            </p>
                        </div>
                    </x-dashboard.card>

                    <x-dashboard.card title="Identity Verification" subtitle="Required before appointment booking.">
                        @php
                            $identityStatus = $identityVerificationStatus ?? 'unverified';
                            $identityLabel = match ($identityStatus) {
                                'pending' => 'Pending Verification',
                                'verified' => 'Verified',
                                'rejected' => 'Rejected',
                                default => 'Unverified',
                            };
                            $identityClass = match ($identityStatus) {
                                'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
                                'verified' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                'rejected' => 'bg-red-50 text-red-700 ring-red-200',
                                default => 'bg-slate-100 text-slate-700 ring-slate-200',
                            };
                        @endphp
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $identityClass }}">{{ $identityLabel }}</span>
                        @if (($latestVerification?->status ?? null) === 'rejected' && $latestVerification?->rejection_reason)
                            <p class="mt-3 text-sm text-red-700">{{ $latestVerification->rejection_reason }}</p>
                        @else
                            <p class="mt-3 text-sm text-slate-600">Upload a valid ID after passing eligibility so admins can verify your donor account.</p>
                        @endif
                        <a href="{{ route('donor.verification.index') }}" class="mt-4 block w-full rounded-xl bg-red-700 px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-red-800">
                            Manage Verification
                        </a>
                    </x-dashboard.card>
                </div>
            </div>
        </section>
    </main>

</div>
</body>
</html>
