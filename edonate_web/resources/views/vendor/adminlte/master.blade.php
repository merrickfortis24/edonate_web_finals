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
            var allowed = { light: true, dark: true };

            function normalize(theme) {
                return allowed[theme] ? theme : 'light';
            }

            function readTheme() {
                try {
                    return normalize(window.localStorage.getItem(storageKey));
                } catch (error) {
                    return 'light';
                }
            }

            function applyTheme(theme) {
                var nextTheme = normalize(theme);
                document.documentElement.setAttribute('data-bs-theme', nextTheme);
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

    @pluginScripts
    @stack('js')
    @yield('js')
</body>
</html>
