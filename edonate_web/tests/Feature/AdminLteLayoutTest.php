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
        $this->assertStringContainsString('Donation Records / Check-in', $html);
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

    public function test_adminlte_theme_is_bootstrapped_before_assets_and_settings_exposes_appearance_control(): void
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
        $this->assertStringContainsString("document.documentElement.setAttribute('data-bs-theme', nextTheme)", $shell);
        $this->assertStringContainsString('settings-appearance-pane', $settings);
        $this->assertStringContainsString('id="settingsThemeToggle"', $settings);
        $this->assertStringContainsString('role="switch"', $settings);
        $this->assertStringContainsString("window.localStorage.setItem('lte-theme', nextTheme)", $settings);
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
