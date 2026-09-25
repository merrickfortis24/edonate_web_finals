@php
    $status = strtolower(trim((string) $event->status));
    $canOpen = $status === 'closed'
        && $event->event_date
        && now()->startOfDay()->lte($event->event_date);
    $canClose = $status === 'open';
    $canComplete = in_array($status, ['open', 'closed'], true);
    $canCancel = in_array($status, ['open', 'closed'], true);
    $hasLifecycleActions = $canOpen || $canClose || $canComplete || $canCancel;
    $dropdownId = 'event-actions-' . $event->event_id;
@endphp

<div class="event-actions">
    <a
        class="event-action-btn event-action-btn--icon event-action-btn--donors text-decoration-none"
        href="{{ route('admin.donation-events.show', $event) }}"
        aria-label="View donors for {{ $event->title }}"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        title="View donors"
    >
        <i class="bi bi-people-fill" aria-hidden="true"></i>
        <span class="visually-hidden">View donors</span>
    </a>

    <button
        class="event-action-btn event-action-btn--icon event-action-btn--edit"
        type="button"
        data-action="edit"
        aria-label="Edit {{ $event->title }}"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        title="Edit event"
    >
        <i class="bi bi-pencil-square" aria-hidden="true"></i>
        <span class="visually-hidden">Edit event</span>
    </button>

    @if ($hasLifecycleActions)
        <div class="dropdown event-action-dropdown">
            <button
                class="event-action-btn event-action-btn--icon event-action-btn--more dropdown-toggle"
                id="{{ $dropdownId }}"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="More actions for {{ $event->title }}"
                title="More actions"
            >
                <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                <span class="visually-hidden">More actions</span>
            </button>

            <ul class="dropdown-menu dropdown-menu-end event-action-menu" aria-labelledby="{{ $dropdownId }}">
                @if ($canOpen)
                    <li>
                        <button class="dropdown-item" type="button" data-action="open">
                            <i class="bi bi-play-circle" aria-hidden="true"></i>
                            Open event
                        </button>
                    </li>
                @endif

                @if ($canClose)
                    <li>
                        <button class="dropdown-item" type="button" data-action="close">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            Close event
                        </button>
                    </li>
                @endif

                @if ($canComplete)
                    <li>
                        <button class="dropdown-item" type="button" data-action="complete">
                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                            Complete event
                        </button>
                    </li>
                @endif

                @if ($canCancel)
                    @if ($canOpen || $canClose || $canComplete)
                        <li><hr class="dropdown-divider"></li>
                    @endif
                    <li>
                        <button class="dropdown-item dropdown-item--danger" type="button" data-action="cancel">
                            <i class="bi bi-x-circle" aria-hidden="true"></i>
                            Cancel event
                        </button>
                    </li>
                @endif
            </ul>
        </div>
    @endif
</div>
