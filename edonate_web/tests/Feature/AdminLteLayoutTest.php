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
        $this->assertStringContainsString('<aside class="app-sidebar edonate-sidebar shadow"', $html);
        $this->assertStringContainsString('edonate-admin-page admin-unauthorized-page', $html);
        $this->assertStringContainsString('User Management', $html);
        $this->assertStringContainsString('User Roles and Permissions', $html);
        $this->assertStringContainsString('edonate-sidebar-logout-form', $html);
        $this->assertStringNotContainsString('id="hamburgerBtn"', $html);
        $this->assertStringNotContainsString('id="sidebar"', $html);
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
        $paginationPosition = strpos($html, 'class="appointment-pagination"');

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

        $this->assertStringContainsString('data-action="check-in"', $html);
        $this->assertStringContainsString('data-action="no-show"', $html);
        $this->assertStringContainsString('Process Donation', $html);
        $this->assertStringNotContainsString('data-action="reschedule"', $html);
        $this->assertStringNotContainsString('id="completeModal"', $html);
        $this->assertStringNotContainsString('id="rescheduleModal"', $html);
        $this->assertFalse(Route::has('admin.appointments.reschedule'));
    }

    public function test_donation_processing_has_no_duplicate_attendance_actions(): void
    {
        $html = $this->renderPageForRole('admin.donor_records', 'admin', '/admin/donation-records');

        $this->assertStringContainsString('Donation Processing', $html);
        $this->assertStringContainsString('Complete Donation', $html);
        $this->assertStringContainsString('Defer On Site', $html);
        $this->assertStringNotContainsString('data-action="check-in"', $html);
        $this->assertStringNotContainsString('data-action="no-show"', $html);
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
