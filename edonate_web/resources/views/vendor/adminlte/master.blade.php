@php
    $layoutFixed = config('adminlte.layout_fixed_sidebar');
    $fixedHeader = config('adminlte.layout_fixed_navbar');
    $fixedFooter = config('adminlte.layout_fixed_footer');
    $rtl = config('adminlte.layout_rtl', false);
    $sidebarBreakpoint = config('adminlte.sidebar_breakpoint', 'lg');
    $sidebarMini = config('adminlte.sidebar_mini');
    $sidebarCollapse = config('adminlte.sidebar_collapse');

    $bodyClasses = collect([
        $layoutFixed ? 'layout-fixed' : null,
        $fixedHeader ? 'fixed-header' : null,
        $fixedFooter ? 'fixed-footer' : null,
        'sidebar-expand-'.$sidebarBreakpoint,
        $sidebarMini ? 'sidebar-mini' : null,
        $sidebarCollapse ? 'sidebar-collapse' : null,
        'bg-body-tertiary',
        config('adminlte.classes_body'),
    ])->filter()->implode(' ');

    $titlePrefix = config('adminlte.title_prefix', '');
    $titlePostfix = config('adminlte.title_postfix', '');
    $title = trim($titlePrefix.' '.($title ?? config('adminlte.title', 'AdminLTE 4')).' '.$titlePostfix);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }}</title>

    <script>
        (function () {
            var storageKey = 'lte-theme';

            function isAllowed(theme) {
                return theme === 'light' || theme === 'dark';
            }

            function normalize(theme) {
                return isAllowed(theme) ? theme : 'light';
            }

            function readTheme() {
                try {
                    var storedTheme = window.localStorage.getItem(storageKey);

                    if (isAllowed(storedTheme)) {
                        return storedTheme;
                    }

                    // AdminLTE's color-mode initializer falls back to the OS
                    // preference when this key is missing. eDonate defaults
                    // to light, so persist that default before AdminLTE runs.
                    window.localStorage.setItem(storageKey, 'light');
                } catch (error) {
                    // Browsers may block localStorage in private or restricted modes.
                }

                return 'light';
            }

            function applyTheme(theme) {
                var nextTheme = normalize(theme);
                document.documentElement.setAttribute('data-bs-theme', nextTheme);
                document.documentElement.style.colorScheme = nextTheme;
                return nextTheme;
            }

            function writeTheme(theme) {
                var nextTheme = applyTheme(theme);

                try {
                    window.localStorage.setItem(storageKey, nextTheme);
                } catch (error) {
                    // Browsers may block localStorage in private or restricted modes.
                }

                window.dispatchEvent(new CustomEvent('edonate:themechange', {
                    detail: { theme: nextTheme },
                }));

                return nextTheme;
            }

            applyTheme(readTheme());

            // AdminLTE applies its own preferred theme at DOMContentLoaded.
            // Re-apply eDonate's default after that initializer so the light
            // default also works when localStorage is unavailable.
            document.addEventListener('DOMContentLoaded', function () {
                window.setTimeout(function () {
                    applyTheme(readTheme());
                }, 0);
            });

            window.eDonateTheme = {
                storageKey: storageKey,
                getTheme: readTheme,
                setTheme: writeTheme,
                applyTheme: applyTheme,
            };
        })();
    </script>

    @hasSection('adminlte_css')
        @yield('adminlte_css')
    @endif

    {{-- Compiled AdminLTE + Bootstrap from your Vite pipeline --}}
    @vite(['resources/css/adminlte.css', 'resources/js/adminlte.js'])

    @if ($rtl)
        {{-- AdminLTE ships a prebuilt RTL stylesheet; published by adminlte:install. --}}
        <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/adminlte.rtl.min.css') }}">
    @endif

    @stack('css')
    @yield('css')
    @pluginStyles
</head>
<body class="{{ $bodyClasses }}">
    @include('adminlte::partials.preloader')

    @include('adminlte::partials.impersonation-banner')

    <div class="app-wrapper">
        @include('adminlte::partials.navbar')
        @include('adminlte::partials.sidebar')

        <main class="app-main">
            @hasSection('content_header')
                <div class="app-content-header {{ config('adminlte.classes_content_header') }}">
                    <div class="container-fluid">
                        @yield('content_header')
                    </div>
                </div>
            @endif

            <div class="app-content {{ config('adminlte.classes_content') }}">
                <div class="container-fluid">
                    @yield('content')
                </div>
            </div>
        </main>

        @include('adminlte::partials.footer')
        @include('adminlte::partials.control-sidebar')
    </div>

    <div class="modal fade" id="edonateLogoutConfirmModal" tabindex="-1" aria-labelledby="edonateLogoutConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="edonateLogoutConfirmTitle">Confirm logout</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to log out?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="{{ route('admin.logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-danger">Yes, log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @pluginScripts
    @stack('js')
    @yield('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalElement = document.getElementById('edonateLogoutConfirmModal');

            if (!modalElement || !window.bootstrap || !window.bootstrap.Modal) {
                return;
            }

            var logoutModal = new window.bootstrap.Modal(modalElement);

            document.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-logout-confirm]');

                if (!trigger) {
                    return;
                }

                event.preventDefault();
                logoutModal.show();
            });
        });
    </script>
</body>
</html>
