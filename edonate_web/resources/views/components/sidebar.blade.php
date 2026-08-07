@props([
    'sidebarId' => 'sidebar',
    'asideAriaLabel' => 'Sidebar navigation',
    'navAriaLabel' => 'Main navigation',
    'linkMode' => 'nav',
    'bottomClass' => 'sidebar__nav-bottom',
    'role' => 'admin',
    'menuItems' => null,
    'utilityItems' => null,
])

@php
    $linkClass = ($linkMode === 'link' ? 'sidebar__link' : 'sidebar__nav-link') . ' d-block text-decoration-none';
    $activeClass = $linkMode === 'link' ? 'sidebar__link--active' : 'sidebar__nav-link--active';
    $logoutFormId = $sidebarId . '-logout-form';

    $currentRole = strtolower((string) $role);
    $portalSubtitle = $currentRole === 'staff' ? 'Staff Portal' : 'Admin Portal';

    $menuItems = $menuItems ?? [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => ['admin.dashboard*'], 'roles' => ['admin']],
        ['label' => 'Dashboard', 'route' => 'staff.dashboard', 'active' => ['staff.dashboard*'], 'roles' => ['staff']],
        ['label' => 'User Management', 'route' => 'admin.users', 'active' => ['admin.users*'], 'roles' => ['admin']],
        ['label' => 'Donor Verification', 'route' => 'admin.donor-verifications.index', 'active' => ['admin.donor-verifications*'], 'roles' => ['admin']],
        ['label' => 'Event Management', 'route' => 'admin.donation-events.index', 'active' => ['admin.donation-events*'], 'roles' => ['admin']],
        ['label' => 'Appointment Management', 'route' => 'admin.appointments', 'active' => ['admin.appointments*'], 'roles' => ['admin', 'staff']],
        ['label' => 'Donation Records / Check-in', 'route' => 'admin.donation-records', 'active' => ['admin.donation-records*'], 'roles' => ['admin', 'staff']],
        ['label' => 'Facility Management', 'route' => 'admin.facilities.index', 'active' => ['admin.facilities*'], 'roles' => ['admin', 'staff']],
        ['label' => 'Blood Requests', 'route' => 'admin.blood-requests.index', 'active' => ['admin.blood-requests*'], 'roles' => ['admin', 'staff']],
        ['label' => 'Blood Availability Mapping', 'route' => 'admin.blood-availability-mapping', 'active' => ['admin.blood-availability-mapping*'], 'roles' => ['admin', 'staff'], 'multiline' => true],
        ['label' => 'Eligibility Review', 'route' => 'admin.eligibility.index', 'active' => ['admin.eligibility.index*'], 'roles' => ['admin', 'staff']],
        ['label' => 'Question Management', 'route' => 'admin.eligibility.questions.index', 'active' => ['admin.eligibility.questions*'], 'roles' => ['admin']],
        ['label' => 'Notification Center', 'route' => 'admin.notification-center', 'active' => ['admin.notification-center*'], 'roles' => ['admin', 'staff']],
        ['label' => 'Report & Analytics', 'route' => 'admin.report-analytics', 'active' => ['admin.report-analytics*'], 'roles' => ['admin']],
        ['label' => 'Audit Logs', 'route' => 'admin.audit-logs', 'active' => ['admin.audit-logs*'], 'roles' => ['admin', 'staff']],
        ['label' => 'RBAC', 'route' => 'admin.rbac', 'active' => ['admin.rbac*'], 'roles' => ['admin']],
    ];

    $utilityItems = $utilityItems ?? [
        ['label' => 'Settings', 'route' => 'admin.settings', 'active' => ['admin.settings*'], 'roles' => ['admin']],
        ['label' => 'Logout', 'route' => 'admin.logout', 'active' => [], 'roles' => ['admin', 'staff'], 'logout' => true],
    ];

    $canRenderItem = static function (array $item) use ($currentRole): bool {
        if (!array_key_exists('roles', $item) || $item['roles'] === null) {
            return true;
        }

        return in_array($currentRole, (array) $item['roles'], true);
    };

    $isActive = static function ($patterns): bool {
        foreach ((array) $patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    };

    $resolveHref = static function (array $item): string {
        if (!empty($item['route'])) {
            return route($item['route']);
        }

        return $item['url'] ?? '#';
    };
@endphp

<aside class="sidebar d-flex flex-column" id="{{ $sidebarId }}" aria-label="{{ $asideAriaLabel }}">
    @if ($linkMode === 'link')
        <div class="sidebar__brand">
            <svg class="sidebar__brand-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M24 6C24 6 9 21.5 9 30.5C9 38.784 15.716 45.5 24 45.5C32.284 45.5 39 38.784 39 30.5C39 21.5 24 6 24 6Z" fill="white"/>
            </svg>
            <div>
                <div class="sidebar__brand-name">eDonate</div>
                <div class="sidebar__brand-sub">{{ $portalSubtitle }}</div>
            </div>
        </div>
    @else
        <div class="sidebar__logo">
            <div class="sidebar__logo-icon" aria-hidden="true">
                <svg width="20" height="24" viewBox="0 0 20 24" xmlns="http://www.w3.org/2000/svg" fill="none">
                    <path d="M10 0C10 0 0 10.2 0 16.2C0 20.5 3.6 24 8 24C12.4 24 16 20.5 16 16.2C16 10.2 10 0 10 0Z" fill="#fff"/>
                </svg>
            </div>
            <div>
                <div class="sidebar__logo-name">eDonate</div>
                <div class="sidebar__logo-sub">{{ $portalSubtitle }}</div>
            </div>
        </div>
    @endif

    <nav class="sidebar__nav d-flex flex-column" aria-label="{{ $navAriaLabel }}">
        <ul class="list-unstyled d-flex flex-column flex-grow-1 mb-0 p-0">
            @foreach ($menuItems as $item)
                @continue(!$canRenderItem($item))

                @php
                    $itemIsActive = $isActive($item['active'] ?? []);
                    $itemClass = trim($linkClass . ($itemIsActive ? ' ' . $activeClass : '') . (!empty($item['multiline']) ? ' sidebar__link--multiline' : ''));
                @endphp

                <li>
                    <a href="{{ $resolveHref($item) }}" class="{{ $itemClass }}" @if($itemIsActive) aria-current="page" @endif>
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach

            @if ($linkMode === 'nav')
                <li><div class="sidebar__divider" role="separator"></div></li>
                @foreach ($utilityItems as $item)
                    @continue(!$canRenderItem($item))

                    <li>
                        @if (!empty($item['logout']))
                            <a href="{{ $resolveHref($item) }}" class="{{ $linkClass }}" onclick="event.preventDefault(); document.getElementById('{{ $logoutFormId }}').submit();">
                                {{ $item['label'] }}
                            </a>
                        @else
                            <a href="{{ $resolveHref($item) }}" class="{{ $linkClass }}">{{ $item['label'] }}</a>
                        @endif
                    </li>
                @endforeach
            @else
                <li class="{{ $bottomClass }}">
                    @foreach ($utilityItems as $item)
                        @continue(!$canRenderItem($item))

                        @if (!empty($item['logout']))
                            <a href="{{ $resolveHref($item) }}" class="{{ $linkClass }} {{ $linkClass === 'sidebar__link' ? 'sidebar__link-button' : '' }}" onclick="event.preventDefault(); document.getElementById('{{ $logoutFormId }}').submit();">
                                {{ $item['label'] }}
                            </a>
                        @else
                            <a href="{{ $resolveHref($item) }}" class="{{ $linkClass }}">{{ $item['label'] }}</a>
                        @endif
                    @endforeach
                </li>
            @endif
        </ul>
    </nav>

    <form id="{{ $logoutFormId }}" method="POST" action="{{ route('admin.logout') }}" style="display: none;">
        @csrf
    </form>
</aside>
