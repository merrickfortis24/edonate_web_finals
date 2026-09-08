<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'Donor Portal' }} | eDonate</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-privacy-assets />
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<a class="ed-skip-link" href="#main-content">Skip to main content</a>
<div class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(220,38,38,0.18),_transparent_42%),radial-gradient(circle_at_top_right,_rgba(248,113,113,0.12),_transparent_36%)]">
    <x-dashboard.nav :links="$navLinks" :current="$activeNav" :userName="$user->first_name" />

    <main id="main-content" tabindex="-1" class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:pl-72 lg:pr-6 lg:py-8">
        <section class="rounded-2xl bg-gradient-to-r from-red-950 via-red-800 to-red-600 p-6 text-white shadow-md">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-red-100">Donor Portal</p>
                    <h1 class="mt-1 text-3xl font-extrabold leading-tight">{{ $pageHeading ?? 'Dashboard' }}</h1>
                    <p class="mt-2 text-sm text-red-100">{{ $pageSubheading ?? 'Manage your donor activities and records.' }}</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-dashboard.stat-card label="Blood Type" :value="$user->blood_type" />
                    <x-dashboard.stat-card label="Total Donations" :value="$totalDonations" />
                </div>
            </div>
        </section>

        @if (session('success'))
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ session('error') }}
            </div>
        @endif

        @if (($notificationBanner ?? null) && ($activeNav ?? '') !== 'alerts')
            <x-dashboard.notification-banner :notification="$notificationBanner" />
        @endif

        <section class="mt-6">
            {{ $slot }}
        </section>
    </main>
</div>
<x-chatbot-widget />
    <x-privacy-controls />
</body>
</html>
