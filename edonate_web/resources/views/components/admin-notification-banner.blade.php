@props(['notification' => null])

@if ($notification)
    <section class="admin-notification-banner mb-4" role="status" aria-live="polite" aria-label="New admin notification">
        <div class="admin-notification-banner__icon" aria-hidden="true"><i class="bi bi-bell-fill"></i></div>
        <div class="admin-notification-banner__content">
            <p class="admin-notification-banner__eyebrow">New notification</p>
            <h2 class="admin-notification-banner__title">{{ $notification['title'] ?? 'Notification' }}</h2>
            <p class="admin-notification-banner__message">{{ $notification['message'] ?? '' }}</p>
            @if (! empty($notification['created_at']))
                <p class="admin-notification-banner__date">{{ $notification['created_at'] }}</p>
            @endif
        </div>
        <a class="admin-notification-banner__action" href="{{ $notification['url'] ?? route('admin.notification-center') }}">Open notifications</a>
    </section>
@endif
