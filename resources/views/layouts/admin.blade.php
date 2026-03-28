@php
    $adminPageClass = trim($__env->yieldContent('admin_page_class'));
    $layoutWrapperClass = trim($__env->yieldContent('layout_wrapper_class'));

    $sidebarId = trim($__env->yieldContent('sidebar_id')) ?: 'sidebar';
    $asideAriaLabel = trim($__env->yieldContent('sidebar_aria_label')) ?: 'Sidebar navigation';
    $navAriaLabel = trim($__env->yieldContent('sidebar_nav_aria_label')) ?: 'Main navigation';
    $sidebarLinkMode = trim($__env->yieldContent('sidebar_link_mode')) ?: 'nav';
    $sidebarBottomClass = trim($__env->yieldContent('sidebar_bottom_class')) ?: 'sidebar__nav-bottom';
    $sidebarRole = trim($__env->yieldContent('sidebar_role')) ?: 'admin';

    $overlayId = trim($__env->yieldContent('overlay_id')) ?: 'overlay';
    $overlayClass = trim($__env->yieldContent('overlay_class')) ?: 'overlay';
    $overlayVisibleClass = trim($__env->yieldContent('overlay_open_class')) ?: 'overlay--visible';

    $hamburgerId = trim($__env->yieldContent('hamburger_id')) ?: 'hamburgerBtn';
    $hamburgerClass = trim($__env->yieldContent('hamburger_class')) ?: 'hamburger';
    $renderDefaultHamburger = strtolower(trim($__env->yieldContent('render_default_hamburger') ?: 'true')) !== 'false';

    $sidebarOpenClass = trim($__env->yieldContent('sidebar_open_class')) ?: 'sidebar--open';

    $headerTitle = trim($__env->yieldContent('header_title'));
    $headerSubtitle = trim($__env->yieldContent('header_subtitle'));
    $headerClass = trim($__env->yieldContent('header_class')) ?: 'header';
    $headerLeftClass = trim($__env->yieldContent('header_left_class')) ?: 'header__left-group';
    $headerRightClass = trim($__env->yieldContent('header_right_class')) ?: 'header__right';
    $headerSlot = $__env->yieldContent('header_slot');
    $headerActions = $__env->yieldContent('header_actions');

    $renderPageHeaderRaw = trim($__env->yieldContent('render_page_header'));
    if ($renderPageHeaderRaw === '') {
        $renderPageHeader = $headerTitle !== '';
    } else {
        $renderPageHeader = strtolower($renderPageHeaderRaw) !== 'false';
    }

    $adminPageData = trim($__env->yieldContent('admin_page_data'));
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'eDonate - Admin')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    @stack('admin_head')
</head>
<body class="admin-page {{ $adminPageClass }}">
<div class="{{ $overlayClass }}" id="{{ $overlayId }}" aria-hidden="true"></div>

@if ($renderDefaultHamburger)
<button
    class="{{ $hamburgerClass }}"
    id="{{ $hamburgerId }}"
    aria-expanded="false"
    aria-controls="{{ $sidebarId }}"
    aria-label="Toggle navigation menu"
>
    <span class="hamburger__bar"></span>
    <span class="hamburger__bar"></span>
    <span class="hamburger__bar"></span>
</button>
@endif

@if ($layoutWrapperClass !== '')
<div class="{{ $layoutWrapperClass }}">
@endif

<x-sidebar
    :sidebar-id="$sidebarId"
    :aside-aria-label="$asideAriaLabel"
    :nav-aria-label="$navAriaLabel"
    :link-mode="$sidebarLinkMode"
    :bottom-class="$sidebarBottomClass"
    :role="$sidebarRole"
/>

@if ($renderPageHeader)
<x-admin-header
    :title="$headerTitle"
    :subtitle="$headerSubtitle !== '' ? $headerSubtitle : null"
    :header-class="$headerClass"
    :left-class="$headerLeftClass"
    :right-class="$headerRightClass"
>
    @if (trim($headerSlot) !== '')
        {!! $headerSlot !!}
    @endif

    @if (trim($headerActions) !== '')
        <x-slot:actions>
            {!! $headerActions !!}
        </x-slot:actions>
    @endif
</x-admin-header>
@endif

@hasSection('main_content')
    @yield('main_content')
@else
    @yield('content')
@endif

@if ($layoutWrapperClass !== '')
</div>
@endif

<script type="application/json" id="adminPageData">{!! $adminPageData !== '' ? $adminPageData : '{}' !!}</script>
<script>
    (function () {
        var payloadElement = document.getElementById('adminPageData');
        if (!payloadElement) {
            window.AdminPageData = {};
            return;
        }

        try {
            window.AdminPageData = JSON.parse(payloadElement.textContent || '{}');
        } catch (error) {
            console.warn('Invalid admin page JSON payload.', error);
            window.AdminPageData = {};
        }
    })();
</script>

<script>
    (function () {
        var btn = document.getElementById(@json($hamburgerId));
        var sidebar = document.getElementById(@json($sidebarId));
        var overlay = document.getElementById(@json($overlayId));

        if (!btn || !sidebar || !overlay) {
            return;
        }

        function setMenuState(isOpen) {
            sidebar.classList.toggle(@json($sidebarOpenClass), isOpen);
            overlay.classList.toggle(@json($overlayVisibleClass), isOpen);
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            overlay.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }

        function toggleMenu() {
            setMenuState(!sidebar.classList.contains(@json($sidebarOpenClass)));
        }

        btn.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', function () {
            setMenuState(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setMenuState(false);
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024) {
                setMenuState(false);
            }
        });
    })();
</script>

@stack('admin_scripts')
</body>
</html>
