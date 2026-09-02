<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminLteLayoutTest extends TestCase
{
    public function test_admin_shell_uses_adminlte_and_admin_navigation(): void
    {
        $html = $this->renderPageForRole('admin.unauthorized', 'admin', '/admin/users');

        $this->assertStringContainsString('<div class="app-wrapper">', $html);
        $this->assertStringContainsString('<title>eDonate - Unauthorized</title>', $html);
        $this->assertStringContainsString('rel="icon" type="image/png"', $html);
        $this->assertStringContainsString('images/edonate-icon.png?v=', $html);
        $this->assertStringContainsString('<aside class="app-sidebar edonate-sidebar shadow"', $html);
        $this->assertStringContainsString('images/edonate-icon.png', $html);
        $this->assertStringContainsString('class="edonate-sidebar-logo"', $html);
        $this->assertStringContainsString('edonate-admin-page admin-unauthorized-page', $html);
        $this->assertStringContainsString('User Management', $html);
        $this->assertStringContainsString('User Roles and Permissions', $html);
        $this->assertStringContainsString('edonate-sidebar-logout-form', $html);
        $this->assertStringNotContainsString('id="hamburgerBtn"', $html);
        $this->assertStringNotContainsString('id="sidebar"', $html);
    }

    public function test_public_login_views_use_the_new_full_edonate_wordmark(): void
    {
        $donorLogin = file_get_contents(resource_path('views/donor/login.blade.php'));
        $adminLogin = file_get_contents(resource_path('views/admin/admin_login.blade.php'));

        $this->assertIsString($donorLogin);
        $this->assertIsString($adminLogin);
        $this->assertStringContainsString("asset('images/edonate-logo.png')", $donorLogin);
        $this->assertStringContainsString("asset('images/edonate-logo.png')", $adminLogin);
        $this->assertStringContainsString('data-password-toggle', $donorLogin);
        $this->assertStringContainsString('data-password-toggle', $adminLogin);
        $this->assertStringContainsString('x-password-toggle-script', $donorLogin);
        $this->assertStringContainsString('x-password-toggle-script', $adminLogin);
        $this->assertStringNotContainsString('card__left__icon', $adminLogin);
    }

    public function test_staff_shell_hides_admin_only_navigation(): void
    {
        $html = $this->renderPageForRole('admin.unauthorized', 'staff', '/staff/dashboard');

        $this->assertStringContainsString('Staff Portal', $html);
        $this->assertStringContainsString('Appointment Management', $html);
        $this->assertStringContainsString('Donation Processing', $html);
        $this->assertStringContainsString('Audit Logs', $html);
        $this->assertStringNotContainsString('User Management', $html);
        $this->assertStringNotContainsString('Question Management', $html);
        $this->assertStringNotContainsString('User Roles and Permissions', $html);
        $this->assertStringNotContainsString('Profile', $html);
        $this->assertStringNotContainsString('Settings', $html);
    }

    public function test_admin_dashboard_keeps_only_the_shared_portal_notification_button(): void
    {
        $html = $this->renderPageForRole('admin.admin_dashboard', 'admin', '/admin/dashboard');

        $this->assertStringContainsString('admin-navbar-notification-link', $html);
        $this->assertStringContainsString('aria-label="Open Notification Center', $html);
        $this->assertStringNotContainsString('aria-label="Go to Notification Center"', $html);
        $this->assertStringContainsString('aria-label="Go to Settings"', $html);
    }

    public function test_admin_profile_uses_the_shared_adminlte_shell_and_existing_rbac_editor_link(): void
    {
        $html = $this->renderPageForRole('admin.profile', 'admin', '/admin/profile');

        $this->assertStringContainsString('<title>eDonate - Admin Profile</title>', $html);
        $this->assertStringContainsString('admin-profile-page', $html);
        $this->assertStringContainsString('Edit Profile', $html);
        $this->assertStringContainsString('admin/profile', $html);
        $this->assertStringContainsString('admin/settings', $html);
    }

    public function test_existing_page_data_and_scripts_are_preserved_inside_the_adminlte_shell(): void
    {
        $html = $this->renderPageForRole('admin.staff_dashboard', 'staff', '/staff/dashboard');
        $payloadPosition = strpos($html, 'id="adminPageData"');
        $pageScriptPosition = strpos($html, 'var dashboardData =');

        $this->assertIsInt($payloadPosition);
        $this->assertIsInt($pageScriptPosition);
        $this->assertLessThan($pageScriptPosition, $payloadPosition);
        $this->assertStringContainsString('"page":"staff-dashboard"', $html);
        $this->assertMatchesRegularExpression('#(?:/build/assets/adminlte-|/resources/js/adminlte\.js)#', $html);
        $this->assertLessThanOrEqual(1, substr_count($html, '/@vite/client'));
    }

    public function test_user_management_keeps_pagination_outside_the_scrollable_donor_grid(): void
    {
        $html = $this->renderPageForRole('admin.user_management', 'admin', '/admin/users');
        $wrapperPosition = strpos($html, 'class="donor-table-wrapper table-responsive"');
        $paginationPosition = strpos($html, 'aria-label="Table pagination"');

        $this->assertIsInt($wrapperPosition);
        $this->assertIsInt($paginationPosition);
        $this->assertStringContainsString('aria-label="Scrollable donor records table"', $html);
        $this->assertStringNotContainsString('class="table-inner table-responsive"', $html);
        $this->assertGreaterThan($wrapperPosition, $paginationPosition);
    }

    public function test_appointment_management_keeps_pagination_outside_the_scrollable_appointment_grid(): void
    {
        $html = $this->renderPageForRole('admin.appointment_management', 'admin', '/admin/appointments');
        $wrapperPosition = strpos($html, 'class="appointment-table-scroll appointment-table-wrapper table-responsive"');
        $paginationPosition = strpos($html, 'class="appointment-pagination admin-pagination');

        $this->assertIsInt($wrapperPosition);
        $this->assertIsInt($paginationPosition);
        $this->assertStringContainsString('aria-label="Scrollable appointment records table"', $html);
        $this->assertStringContainsString('class="appointment-table-inner"', $html);
        $this->assertGreaterThan($wrapperPosition, $paginationPosition);
    }

    public function test_user_management_exposes_clear_digital_id_edit_and_deactivate_actions(): void
    {
        $html = $this->renderPageForRole('admin.user_management', 'admin', '/admin/users');

        $this->assertStringContainsString('Digital Donor ID', $html);
        $this->assertStringContainsString('digital-id-card--component', $html);
        $this->assertStringContainsString('data-digital-id-card', $html);
        $this->assertStringContainsString('data-digital-id-field="nextEligibleDate"', $html);
        $this->assertStringContainsString('userManagementViewCardAddress', $html);
        $this->assertStringContainsString('userManagementViewCardContactNumber', $html);
        $this->assertStringContainsString('userManagementViewCardLastDonationDate', $html);
        $this->assertStringContainsString('userManagementViewCardNextEligibleDate', $html);
        $this->assertStringContainsString('function ensureModal(element, current)', $html);
        $this->assertStringContainsString('viewModal = ensureModal(viewModalElement, viewModal)', $html);
        $this->assertStringContainsString('userManagementExportButton', $html);
        $this->assertTrue(Route::has('admin.users.export'));
        $this->assertStringContainsString('title="View Digital Donor ID"', $html);
        $this->assertStringContainsString('title="Edit Donor"', $html);
        $this->assertStringContainsString('title="Deactivate Donor"', $html);
        $this->assertStringContainsString('deactivateUrlTemplate', $html);
        $this->assertStringNotContainsString('data-action="delete"', $html);
        $this->assertStringNotContainsString('deleteUrlTemplate', $html);
        $this->assertTrue(Route::has('admin.users.deactivate'));
        $this->assertFalse(Route::has('admin.users.delete'));
    }

    public function test_blood_request_details_has_a_direct_back_to_list_action(): void
    {
        $html = view('admin.blood_request_show', [
            'bloodRequest' => (object) ['request_id' => 1],
            'details' => [
                'request' => [
                    'request_reference' => 'DEMO-BR-0001',
                    'facility_name' => 'Demo Facility',
                    'request_type' => 'blood_request',
                    'needed_blood_type' => 'O+',
                    'required_donors' => 1,
                    'specific_match_required' => 'Yes',
                    'allow_other_blood_types' => false,
                    'urgency' => 'normal',
                    'status' => 'open',
                ],
                'inventory' => ['available_units' => 0],
                'summary' => [
                    'exact_matches_found' => 0,
                    'other_eligible_candidates' => 0,
                    'notified' => 0,
                    'interested' => 0,
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Back to Blood Requests', $html);
        $this->assertStringContainsString(route('admin.blood-requests.index'), $html);
    }

    public function test_appointment_management_is_the_attendance_action_page(): void
    {
        $html = $this->renderPageForRole('admin.appointment_management', 'admin', '/admin/appointments');

        $this->assertStringNotContainsString('Calendar View', $html);
        $this->assertStringNotContainsString('action="#"', $html);
        $this->assertStringContainsString('data-action="check-in"', $html);
        $this->assertStringContainsString('data-action="no-show"', $html);
        $this->assertStringContainsString('Process Donation', $html);
        $this->assertStringNotContainsString('data-action="reschedule"', $html);
        $this->assertStringNotContainsString('id="completeModal"', $html);
        $this->assertStringNotContainsString('id="rescheduleModal"', $html);
        $this->assertFalse(Route::has('admin.appointments.reschedule'));
    }

    public function test_admin_settings_expose_persisted_action_endpoints(): void
    {
        $html = $this->renderPageForRole('admin.settings', 'admin', '/admin/settings');

        $this->assertStringContainsString('admin/settings/general', $html);
        $this->assertStringContainsString('admin/settings/account', $html);
        $this->assertTrue(Route::has('admin.settings.general.update'));
        $this->assertTrue(Route::has('admin.settings.account.update'));
    }

    public function test_notification_banners_are_available_for_admin_and_donor_inboxes(): void
    {
        $adminBanner = view('components.admin-notification-banner', [
            'notification' => [
                'title' => 'Donor Verification Approved',
                'message' => 'A donor verification was approved.',
                'url' => route('admin.notification-center'),
            ],
        ])->render();
        $donorBanner = view('components.dashboard.notification-banner', [
            'notification' => (object) [
                'notification_type' => 'donor_verification_approved',
                'message' => 'Your identity verification has been approved.',
                'created_at' => now(),
            ],
        ])->render();

        $this->assertStringContainsString('Open notifications', $adminBanner);
        $this->assertStringContainsString(route('admin.notification-center'), $adminBanner);
        $this->assertStringContainsString('View notifications', $donorBanner);
        $this->assertStringContainsString(route('donor.alerts'), $donorBanner);
    }

    public function test_rbac_shows_fixed_permission_access_as_read_only(): void
    {
        $html = $this->renderPageForRole('admin.rbac', 'admin', '/admin/rbac');

        $this->assertStringContainsString('Permission Access Overview', $html);
        $this->assertStringContainsString('read-only view of the configured permission matrix', $html);
        $this->assertStringNotContainsString('id="rbacSavePermissionsBtn"', $html);
        $this->assertStringNotContainsString('id="rbacHeaderAddRoleBtn"', $html);
        $this->assertStringNotContainsString('id="rbacInlineAddRoleBtn"', $html);
    }

    public function test_donation_processing_has_no_duplicate_attendance_actions(): void
    {
        $html = $this->renderPageForRole('admin.donor_records', 'admin', '/admin/donation-records');

        $this->assertStringContainsString('Donation Processing', $html);
        $this->assertStringContainsString('Complete Donation', $html);
        $this->assertStringContainsString('Defer On Site', $html);
        $this->assertStringContainsString('function ensureModal', $html);
        $this->assertStringContainsString('deferModal = ensureModal(deferModalElement, deferModal)', $html);
        $this->assertStringContainsString('getOrCreateInstance(element)', $html);
        $this->assertStringNotContainsString('new window.bootstrap.Modal(deferModalElement)', $html);
        $this->assertStringContainsString('completePageUrlTemplate', $html);
        $this->assertStringContainsString('title="Complete Donation"', $html);
        $this->assertStringContainsString('aria-label="Complete Donation"', $html);
        $this->assertStringNotContainsString('target="_blank"', $html);
        $this->assertStringNotContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringNotContainsString('function openCompletionWindow', $html);
        $this->assertStringNotContainsString('window.open(', $html);
        $this->assertStringNotContainsString('id="completeDonationModal"', $html);
        $this->assertStringNotContainsString('id="completeDonationForm"', $html);
        $this->assertStringNotContainsString('data-action="check-in"', $html);
        $this->assertStringNotContainsString('data-action="no-show"', $html);
    }

    public function test_complete_donation_page_provides_camera_and_digital_id_fields(): void
    {
        $html = view('admin.complete_donation', [
            'completionPayload' => [
                'appointment' => [
                    'appointment_id' => 7,
                    'appointment_code' => 'AP007',
                    'appointment_date' => '2026-08-25',
                ],
                'donor' => [
                    'donor_id' => 7,
                    'donor_code' => 'DN-000007',
                    'full_name' => 'Maria Santos',
                    'blood_type' => 'O+',
                    'blood_type_status' => 'verified',
                    'verification_status' => 'verified',
                    'full_address' => 'Demo Barangay, Lipa City, Batangas',
                    'contact_number' => '09000000007',
                    'last_donation_date' => '2026-06-01',
                    'next_eligible_date' => '2026-07-27',
                ],
                'canVerifyBloodType' => true,
                'verificationBloodTypes' => [],
                'api' => [
                    'completeUrl' => route('admin.appointments.complete', ['appointment' => 7]),
                    'returnUrl' => route('admin.donation-records'),
                ],
            ],
        ])->render();

        $this->assertTrue(Route::has('admin.appointments.complete-page'));
        $route = Route::getRoutes()->getByName('admin.appointments.complete-page');
        $this->assertNotNull($route);
        $this->assertSame('admin/appointments/{appointment}/complete', $route->uri());
        $this->assertContains('admin.auth', $route->gatherMiddleware());
        $this->assertContains('admin.role:admin,staff', $route->gatherMiddleware());
        $this->assertContains('throttle:admin-api', $route->gatherMiddleware());
        $this->assertStringContainsString('Digital Donor ID', $html);
        $this->assertStringContainsString('Enable Camera', $html);
        $this->assertStringContainsString('Take Photo', $html);
        $this->assertStringContainsString('completionDonorName', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('Blood Type', $html);
        $this->assertStringContainsString('Donor ID', $html);
        $this->assertStringContainsString('Address', $html);
        $this->assertStringContainsString('Contact Number', $html);
        $this->assertStringContainsString('Last Donation Date', $html);
        $this->assertStringContainsString('Next Eligible Donation Date', $html);
        $this->assertStringContainsString('navigator.mediaDevices.getUserMedia', $html);
        $this->assertStringContainsString('returnToProcessing', $html);
        $this->assertStringContainsString("window.location.replace(api.returnUrl + separator + 'completed=1')", $html);
    }

    public function test_adminlte_theme_is_bootstrapped_before_assets_and_defaults_to_light(): void
    {
        $shell = $this->renderPageForRole('admin.unauthorized', 'admin', '/admin/settings');
        $settings = $this->renderPageForRole('admin.settings', 'admin', '/admin/settings');
        $themeScriptPosition = strpos($shell, "var storageKey = 'lte-theme'");
        $viteAssetPosition = strpos($shell, 'resources/css/adminlte.css');

        if ($viteAssetPosition === false) {
            $viteAssetPosition = strpos($shell, '/build/assets/adminlte-');
        }

        $this->assertIsInt($themeScriptPosition);
        $this->assertIsInt($viteAssetPosition);
        $this->assertLessThan($viteAssetPosition, $themeScriptPosition);
        $this->assertStringContainsString("document.documentElement.setAttribute('data-bs-theme', theme)", $shell);
        $this->assertStringContainsString("var theme = 'light';", $shell);
        $this->assertStringContainsString("window.localStorage.setItem(storageKey, theme)", $shell);
        $this->assertStringNotContainsString('settings-appearance-pane', $settings);
        $this->assertStringNotContainsString('settingsThemeSelect', $settings);
        $this->assertStringNotContainsString('Automatic', $settings);
    }

    public function test_every_configured_adminlte_menu_route_exists(): void
    {
        $inspect = function (array $items) use (&$inspect): void {
            foreach ($items as $item) {
                if (isset($item['route'])) {
                    $this->assertTrue(Route::has($item['route']), "Missing menu route [{$item['route']}].");
                }

                if (isset($item['submenu'])) {
                    $inspect($item['submenu']);
                }
            }
        };

        $inspect(config('adminlte.menu', []));
    }

    private function renderPageForRole(string $view, string $role, string $path): string
    {
        $session = $this->app['session.store'];
        $session->start();
        $session->put([
            'admin_id' => 1,
            'admin_role' => $role,
            'admin_username' => $role,
            'admin_full_name' => ucfirst($role).' User',
        ]);

        $request = Request::create($path);
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        return view($view)->render();
    }
}
