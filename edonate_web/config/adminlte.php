<?php

use App\Support\AdminLte\AdminRoleFilter;
use ColorlibHQ\AdminLte\Menu\Filters\ActiveFilter;
use ColorlibHQ\AdminLte\Menu\Filters\GateFilter;
use ColorlibHQ\AdminLte\Menu\Filters\HrefFilter;
use ColorlibHQ\AdminLte\Menu\Filters\SearchFilter;

return [
    'title' => 'eDonate Admin Portal',
    'title_prefix' => '',
    'title_postfix' => '',

    'use_ico_only' => false,
    'use_full_favicon' => true,
    'google_fonts' => ['allowed' => true],

    'logo' => '<i class="bi bi-droplet-fill text-danger me-2" aria-hidden="true"></i><strong>eDonate</strong>',
    'logo_img' => false,
    'logo_img_class' => '',
    'logo_img_alt' => 'eDonate',
    'auth_logo' => ['enabled' => false],

    // Authentication is session-based in this application, so the stock
    // Laravel Auth user menu is replaced by an eDonate navbar partial.
    'usermenu_enabled' => false,
    'usermenu_header' => false,
    'usermenu_header_class' => 'bg-danger',
    'usermenu_image' => false,
    'usermenu_desc' => false,
    'usermenu_profile_url' => false,

    'layout_topnav' => null,
    'layout_boxed' => null,
    'layout_fixed_sidebar' => true,
    'layout_fixed_navbar' => true,
    'layout_fixed_footer' => null,
    'layout_dark_mode' => false,
    'layout_rtl' => false,

    'footer_left' => 'Copyright &copy; '.date('Y').' <strong>eDonate</strong>. All rights reserved.',
    'footer_right' => 'AdminLTE 4',
    'preloader' => false,
    'control_sidebar' => false,
    'control_sidebar_theme' => 'dark',

    'sidebar_docs_url' => false,
    'demo' => false,
    'demo_middleware' => ['web', 'auth'],
    'docs' => false,
    'docs_middleware' => ['web'],

    'sidebar_breakpoint' => 'lg',
    'sidebar_mini' => true,
    'sidebar_collapse' => false,
    'sidebar_collapse_auto_size' => false,
    'sidebar_scrollbar_theme' => 'os-theme-light',
    'sidebar_scrollbar_auto_hide' => 'leave',
    'sidebar_theme' => 'dark',

    'classes_body' => 'edonate-adminlte',
    'classes_brand' => 'edonate-brand',
    'classes_brand_text' => 'fw-normal',
    'classes_content_wrapper' => '',
    'classes_content_header' => 'border-bottom bg-body',
    'classes_content' => '',
    'classes_sidebar' => 'edonate-sidebar shadow',
    'classes_sidebar_nav' => 'nav-compact',
    'classes_topnav' => 'navbar-expand bg-body shadow-sm border-bottom',
    'classes_topnav_nav' => 'navbar',
    'classes_topnav_container' => 'container-fluid',
    'color_mode_toggle' => false,

    'menu' => [
        [
            'text' => 'Dashboard',
            'route' => 'admin.dashboard',
            'active' => ['admin/dashboard'],
            'icon' => 'bi bi-speedometer2',
            'roles' => ['admin'],
        ],
        [
            'text' => 'Dashboard',
            'route' => 'staff.dashboard',
            'active' => ['staff/dashboard'],
            'icon' => 'bi bi-speedometer2',
            'roles' => ['staff'],
        ],

        ['header' => 'MANAGEMENT', 'roles' => ['admin', 'staff']],
        [
            'text' => 'Management',
            'icon' => 'bi bi-grid-1x2-fill',
            'roles' => ['admin', 'staff'],
            'submenu' => [
                ['text' => 'User Management', 'route' => 'admin.users', 'active' => ['admin/users*'], 'icon' => 'bi bi-people', 'roles' => ['admin']],
                ['text' => 'Donor Verification', 'route' => 'admin.donor-verifications.index', 'active' => ['admin/donor-verifications*'], 'icon' => 'bi bi-person-check', 'roles' => ['admin']],
                ['text' => 'Event Management', 'route' => 'admin.donation-events.index', 'active' => ['admin/donation-events*'], 'icon' => 'bi bi-calendar-event', 'roles' => ['admin']],
                ['text' => 'Appointment Management', 'route' => 'admin.appointments', 'active' => ['admin/appointments*'], 'icon' => 'bi bi-calendar-check', 'roles' => ['admin', 'staff']],
                ['text' => 'Donation Records / Check-in', 'route' => 'admin.donation-records', 'active' => ['admin/donation-records*'], 'icon' => 'bi bi-clipboard2-pulse', 'roles' => ['admin', 'staff']],
                ['text' => 'Facility Management', 'route' => 'admin.facilities.index', 'active' => ['admin/facilities*'], 'icon' => 'bi bi-hospital', 'roles' => ['admin', 'staff']],
                ['text' => 'Blood Requests', 'route' => 'admin.blood-requests.index', 'active' => ['admin/blood-requests*'], 'icon' => 'bi bi-droplet-half', 'roles' => ['admin', 'staff']],
                ['text' => 'Blood Availability Mapping', 'route' => 'admin.blood-availability-mapping', 'active' => ['admin/blood-availability*', 'admin/map*'], 'icon' => 'bi bi-geo-alt', 'roles' => ['admin', 'staff']],
            ],
        ],

        ['header' => 'ELIGIBILITY', 'roles' => ['admin']],
        [
            'text' => 'Eligibility',
            'icon' => 'bi bi-heart-pulse',
            'roles' => ['admin'],
            'submenu' => [
                ['text' => 'Eligibility Review', 'route' => 'admin.eligibility.index', 'active_routes' => ['admin.eligibility.index', 'admin.eligibility.show'], 'icon' => 'bi bi-clipboard2-check', 'roles' => ['admin']],
                ['text' => 'Question Management', 'route' => 'admin.eligibility.questions.index', 'active' => ['admin/eligibility/questions*'], 'icon' => 'bi bi-ui-checks', 'roles' => ['admin']],
            ],
        ],

        ['header' => 'COMMUNICATION', 'roles' => ['admin', 'staff']],
        [
            'text' => 'Notification Center',
            'route' => 'admin.notification-center',
            'active' => ['admin/notification-center', 'admin/notifications*'],
            'icon' => 'bi bi-bell',
            'roles' => ['admin', 'staff'],
        ],

        ['header' => 'REPORTS', 'roles' => ['admin', 'staff']],
        [
            'text' => 'Reports',
            'icon' => 'bi bi-bar-chart-line',
            'roles' => ['admin', 'staff'],
            'submenu' => [
                ['text' => 'Report & Analytics', 'route' => 'admin.report-analytics', 'active' => ['admin/report-analytics*'], 'icon' => 'bi bi-graph-up-arrow', 'roles' => ['admin']],
                ['text' => 'Audit Logs', 'route' => 'admin.audit-logs', 'active' => ['admin/audit-logs*'], 'icon' => 'bi bi-journal-text', 'roles' => ['admin', 'staff']],
            ],
        ],

        ['header' => 'ADMINISTRATION', 'roles' => ['admin']],
        [
            'text' => 'Administration',
            'icon' => 'bi bi-shield-lock',
            'roles' => ['admin'],
            'submenu' => [
                ['text' => 'RBAC', 'route' => 'admin.rbac', 'active' => ['admin/rbac*'], 'icon' => 'bi bi-person-lock', 'roles' => ['admin']],
                ['text' => 'Settings', 'route' => 'admin.settings', 'active' => ['admin/settings*'], 'icon' => 'bi bi-gear', 'roles' => ['admin']],
            ],
        ],

        [
            'text' => 'Logout',
            'route' => 'admin.logout',
            'icon' => 'bi bi-box-arrow-right',
            'roles' => ['admin', 'staff'],
            'logout' => true,
        ],
    ],

    'filters' => [
        AdminRoleFilter::class,
        GateFilter::class,
        HrefFilter::class,
        ActiveFilter::class,
        SearchFilter::class,
    ],

    'plugins' => [
        'flatpickr' => ['enabled' => false, 'css' => 'vendor/flatpickr/flatpickr.min.css', 'js' => 'vendor/flatpickr/flatpickr.min.js'],
        'tom_select' => ['enabled' => false, 'css' => 'vendor/tom-select/tom-select.bootstrap5.min.css', 'js' => 'vendor/tom-select/tom-select.complete.min.js'],
        'tabulator' => ['enabled' => false, 'css' => 'vendor/tabulator-tables/tabulator.min.css', 'js' => 'vendor/tabulator-tables/tabulator.min.js'],
        'quill' => ['enabled' => false, 'css' => 'vendor/quill/quill.snow.css', 'js' => 'vendor/quill/quill.min.js'],
        'apexcharts' => ['enabled' => false, 'js' => 'vendor/apexcharts/apexcharts.min.js'],
        'jsvectormap' => ['enabled' => false, 'css' => 'vendor/jsvectormap/jsvectormap.min.css', 'js' => ['vendor/jsvectormap/jsvectormap.min.js', 'vendor/jsvectormap/maps/world.js']],
        'fullcalendar' => ['enabled' => false, 'css' => 'vendor/fullcalendar/index.global.min.css', 'js' => 'vendor/fullcalendar/index.global.min.js'],
        'sortablejs' => ['enabled' => false, 'js' => 'vendor/sortablejs/sortablejs.min.js'],
    ],
];
