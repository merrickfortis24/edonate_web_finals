@props(['notification' => null])

@if ($notification)
    @php
        $notificationType = trim((string) ($notification->notification_type ?? 'system')) ?: 'system';
        $notificationLabel = \Illuminate\Support\Str::headline(str_replace('_', ' ', $notificationType));
    @endphp

    <section class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-amber-950 shadow-sm sm:px-5" role="status" aria-live="polite" aria-label="New notification">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700" aria-hidden="true">!</span>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-amber-700">New notification</p>
                    <p class="mt-1 text-sm font-bold">{{ $notificationLabel }}</p>
                    <p class="mt-1 text-sm leading-6 text-amber-900">{{ $notification->message }}</p>
                    @if ($notification->created_at)
                        <p class="mt-1 text-xs text-amber-700">{{ \Carbon\Carbon::parse($notification->created_at)->format('M j, Y g:i A') }}</p>
                    @endif
                </div>
            </div>
            <a href="{{ route('donor.alerts') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                View notifications
            </a>
        </div>
    </section>
@endif
