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
        config(['app.url' => 'http://audit.test', 'privacy.ai_enabled' => true,
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
        $cases = [
            'privacy' => ['donor.privacy', []],
            'terms' => ['donor.terms', []],
            'cookies' => ['legal.cookies', []],
            'signup' => ['donor.signup', []],
            'login' => ['donor.login', []],
            'admin-login' => ['admin.admin_login', []],
            'admin-dashboard' => ['admin.admin_dashboard', []],
            'admin-users' => ['admin.user_management', []],
            'admin-facilities' => ['admin.facilities', ['canManage' => true, 'facilityTypes' => ['hospital', 'clinic']]],
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
            if (getenv('EDONATE_EXPORT_A11Y_FIXTURES') === '1') {
                File::ensureDirectoryExists(base_path('tests/browser/fixtures'));
                File::put(base_path('tests/browser/fixtures/'.$name.'.html'), $html);
            }
        }
    }
}
