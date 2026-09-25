@php
    $adminRole = strtolower((string) session('admin_role', 'admin'));
    $adminName = trim((string) (session('admin_full_name') ?: session('admin_username') ?: 'eDonate User'));
    $dashboardUrl = $adminRole === 'staff' ? route('staff.dashboard') : route('admin.dashboard');
    $initials = collect(preg_split('/\s+/', $adminName) ?: [])
        ->filter()
        ->take(2)
        ->map(static fn (string $part): string => strtoupper(substr($part, 0, 1)))
        ->implode('');
@endphp

@php
    $adminUnreadNotificationCount = max(0, (int) ($adminUnreadNotificationCount ?? 0));
    $adminNotificationBadgeLabel = $adminUnreadNotificationCount > 9 ? '9+' : (string) $adminUnreadNotificationCount;
@endphp

<nav class="app-header {{ config('adminlte.classes_topnav', 'navbar-expand bg-body') }} navbar">
    <div class="{{ config('adminlte.classes_topnav_container', 'container-fluid') }}">
        <ul class="navbar-nav align-items-center">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle sidebar">
                    <i class="bi bi-list fs-5" aria-hidden="true"></i>
                </a>
            </li>
            <li class="nav-item d-none d-sm-block">
                <a href="{{ $dashboardUrl }}" class="nav-link">
                    <i class="bi bi-house-heart me-1" aria-hidden="true"></i>
                    {{ $adminRole === 'staff' ? 'Staff Portal' : 'Admin Portal' }}
                </a>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto align-items-center">
            <li class="nav-item">
                <a class="nav-link admin-navbar-notification-link" href="{{ route('admin.notification-center') }}" aria-label="Open Notification Center{{ $adminUnreadNotificationCount > 0 ? ', ' . $adminUnreadNotificationCount . ' unread notifications' : '' }}">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    @if ($adminUnreadNotificationCount > 0)
                        <span class="badge rounded-pill text-bg-danger admin-navbar-notification-badge" aria-hidden="true">{{ $adminNotificationBadgeLabel }}</span>
                        <span class="visually-hidden">{{ $adminUnreadNotificationCount }} unread notifications</span>
                    @endif
                </a>
            </li>
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="edonate-user-avatar" aria-hidden="true">{{ $initials ?: 'ED' }}</span>
                    <span class="d-none d-md-inline text-truncate" style="max-width: 180px">{{ $adminName }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li class="px-3 py-2 border-bottom">
                        <div class="fw-semibold text-truncate" style="max-width: 230px">{{ $adminName }}</div>
                        <small class="text-body-secondary text-capitalize">{{ $adminRole }}</small>
                    </li>
                    @if ($adminRole === 'admin')
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.profile') }}">
                                <i class="bi bi-person-circle me-2" aria-hidden="true"></i>Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.settings') }}">
                                <i class="bi bi-gear me-2" aria-hidden="true"></i>Settings
                            </a>
                        </li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button class="dropdown-item text-danger" type="button" data-logout-confirm>
                            <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Logout
                        </button>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
