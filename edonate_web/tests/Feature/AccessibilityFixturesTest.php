<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AccessibilityFixturesTest extends TestCase
{
    public function test_representative_templates_render_with_synthetic_data_only(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Http::preventStrayRequests();
        config(['app.url' => 'http://audit.test',
            'services.firebase.web' => ['api_key' => '', 'auth_domain' => '', 'project_id' => '', 'app_id' => '']]);
        $this->app['url']->forceRootUrl('http://audit.test');
        $session = $this->app['session.store'];
        $session->start();
        $session->put(['admin_id' => 1, 'admin_role' => 'admin', 'admin_username' => 'test', 'admin_full_name' => 'Test Administrator']);
        $request = Request::create('http://audit.test/admin/users');
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);
        view()->share('errors', new ViewErrorBag());
        $donor = ['navLinks' => [['key' => 'home', 'href' => '/donor/dashboard', 'label' => 'Dashboard']],
            'activeNav' => 'home', 'user' => (object) ['first_name' => 'Test Donor', 'blood_type' => 'O+'],
            'totalDonations' => 0, 'latestEligibility' => null];
        $reportMetrics = [
            'total_donations', 'verified_donors', 'completed_donations', 'deferred_donations', 'failed_donations', 'success_rate',
            'donors_in_period', 'eligible_donors', 'upcoming_appointments', 'appointments_in_period', 'no_shows', 'deferred_on_site',
            'open_requests', 'emergency_requests', 'fulfilled_requests', 'events_in_period', 'low_stock_blood_types',
            'out_of_stock_blood_types', 'total_inventory_units', 'pending_verification', 'verified_donor_accounts',
        ];
        $reportSummary = array_fill_keys($reportMetrics, 0);
        $reportSummary['total_donations'] = 2;
        $reportSummary['completed_donations'] = 1;
        $reportSummary['donors_in_period'] = 1;
        $reportSummary['total_inventory_units'] = 10;
        $reportAvailability = array_fill_keys($reportMetrics, true);
        $reportFixturePayload = [
            'period' => ['range' => 'year', 'start' => '2026-01-01', 'end' => '2026-12-31', 'label' => 'This year'],
            'summary' => $reportSummary,
            'availability' => ['summary' => $reportAvailability],
            'trend' => ['labels' => ['Sep 2026'], 'donors' => [1], 'donors_available' => true, 'donors_message' => null, 'donations' => [1], 'granularity' => 'month'],
            'distribution' => ['available' => true, 'basis' => 'verified', 'verified_total' => 1, 'self_reported_total' => 0, 'unknown_total' => 0, 'items' => [['label' => 'A+', 'count' => 1, 'self_reported_count' => 0]]],
            'inventory' => [['blood_type_id' => 1, 'blood_type' => 'A+', 'available_units' => 10, 'reserved_units' => 1, 'open_request_demand' => 2, 'status' => 'available', 'status_label' => 'Available']],
            'inventory_snapshot_at' => now()->toIso8601String(),
            'filters' => ['range' => 'year', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'facility_id' => null, 'blood_type_id' => null,
                'facilities' => [['value' => 1, 'label' => 'Test Facility'], ['value' => 2, 'label' => 'Other Facility']],
                'blood_types' => [['value' => 1, 'label' => 'A+'], ['value' => 2, 'label' => 'A-']]],
        ];
        $cases = [
            'privacy' => ['donor.privacy', []],
            'terms' => ['donor.terms', []],
            'cookies' => ['legal.cookies', []],
            'signup' => ['donor.signup', []],
            'login' => ['donor.login', []],
            'admin-login' => ['admin.admin_login', []],
            'admin-dashboard' => ['admin.admin_dashboard', []],
            'admin-users' => ['admin.user_management', []],
            'admin-report-analytics' => ['admin.report_analytics', [
                'reportPayload' => $reportFixturePayload,
                'reportApi' => ['dataUrl' => '/admin/report-analytics/data', 'exportUrl' => '/admin/report-analytics/export', 'initialExportUrl' => '/admin/report-analytics/export?range=year'],
            ]],
            'admin-facilities' => ['admin.facilities', ['canManage' => true, 'placesConfigured' => true, 'facilityTypes' => ['hospital', 'clinic']]],
            'admin-map' => ['admin.blood_availability_mapping', ['bloodTypes' => ['A+', 'O-'], 'facilityTypes' => ['hospital']]],
            'admin-blood-requests' => ['admin.blood_requests', ['canManage' => true, 'bloodTypes' => collect(), 'facilities' => collect()]],
            'donor-screening' => ['portal.check-eligibility', $donor + [
                'nextEligibleDate' => null, 'latestDonationDate' => null,
                'screeningQuestions' => collect([(object) ['question_id' => 1, 'question_order' => 1,
                    'question_text' => 'Have you read the screening instructions?', 'followup_prompt' => null]]),
            ]],
            'donor-verification' => ['portal.verification', $donor + [
                'hasPassedEligibility' => true, 'latestVerification' => null, 'verificationHistory' => collect(),
                'documentTypes' => ['government_id' => 'Government ID'],
            ]],
            'donor-booking' => ['portal.book-appointment', $donor + [
                'canBookAppointment' => true, 'identityVerificationStatus' => 'verified', 'appointments' => collect(),
                'eventOptions' => collect([['event_id' => 1, 'title' => 'Test donation event',
                    'event_date' => '2026-10-01', 'start_time' => '09:00', 'end_time' => '12:00',
                    'location_name' => 'Test center', 'address' => 'Test public venue', 'remaining_slots' => 10]]),
            ]],
        ];
        foreach ($cases as $name => [$view, $data]) {
            if (str_starts_with($name, 'donor-')) {
                $session->forget(['admin_id', 'admin_role', 'admin_username', 'admin_full_name']);
                $session->put('donor_id', 1);
            }
            $html = view($view, $data)->render();
            $this->assertStringContainsString('ed-privacy-panel', $html, $name);
            $this->assertStringContainsString('Skip to main content', $html, $name);
            $this->assertStringNotContainsString('<script src="https://www.gstatic.com', $html, $name);
            if (getenv('EDONATE_EXPORT_A11Y_FIXTURES') === '1'
                || (getenv('EDONATE_EXPORT_REPORT_FIXTURE') === '1' && $name === 'admin-report-analytics')
                || (getenv('EDONATE_EXPORT_BLOOD_REQUEST_FIXTURE') === '1' && $name === 'admin-blood-requests')) {
                $fixturePath = $name === 'admin-report-analytics'
                    ? base_path('tests/browser/report-analytics.html')
                    : base_path('tests/browser/fixtures/'.$name.'.html');
                File::ensureDirectoryExists(dirname($fixturePath));
                File::put($fixturePath, $html);
            }
        }
    }
}
