<?php

use App\Http\Controllers\Admin\BloodRequestController as AdminBloodRequestController;
use App\Http\Controllers\Admin\DonationEventController as AdminDonationEventController;
use App\Http\Controllers\Admin\DonorVerificationController as AdminDonorVerificationController;
use App\Http\Controllers\Admin\FacilityController as AdminFacilityController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\DonorDashboardController;
use App\Http\Controllers\DonorLoginController;
use App\Http\Controllers\DonorPortalController;
use App\Http\Controllers\DonorSignupController;
use App\Http\Controllers\DonorVerificationController;
use App\Http\Controllers\EligibilityController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('/signup', [DonorSignupController::class, 'create'])->name('donor.signup');
Route::post('/signup', [DonorSignupController::class, 'store'])
    ->middleware('throttle:registration')
    ->name('donor.signup.store');
Route::get('/signup/check-email', [DonorSignupController::class, 'checkEmail'])
    ->middleware('throttle:public-api')
    ->name('donor.signup.check-email');
Route::post('/signup/send-otp', [DonorSignupController::class, 'sendOtp'])
    ->middleware(['throttle:registration', 'throttle:otp-send'])
    ->name('donor.signup.send-otp');
Route::post('/signup/confirm-otp', [DonorSignupController::class, 'confirmOtp'])
    ->middleware('throttle:otp-verify')
    ->name('donor.signup.confirm-otp');

Route::get('/login', [DonorLoginController::class, 'create'])->name('donor.login');
Route::post('/login', [DonorLoginController::class, 'store'])
    ->middleware('throttle:donor-login')
    ->name('donor.login.store');
Route::post('/auth/google', [SocialAuthController::class, 'handleGoogleLogin'])
    ->middleware('throttle:donor-login')
    ->name('auth.google');
Route::get('/dashboard', [DonorDashboardController::class, 'index'])
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.dashboard');
Route::get('/appointments/book', [DonorPortalController::class, 'bookAppointment'])
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.book-appointment');
Route::get('/appointments/events', [DonorPortalController::class, 'availableEvents'])
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.appointments.events');
Route::post('/appointments/book', [DonorPortalController::class, 'storeAppointment'])
    ->middleware(['donor.active', 'throttle:appointment-write'])
    ->name('donor.book-appointment.store');
Route::patch('/appointments/{appointment}/cancel', [DonorPortalController::class, 'cancelAppointment'])
    ->whereNumber('appointment')
    ->middleware(['donor.active', 'throttle:appointment-write'])
    ->name('donor.appointments.cancel');
Route::get('/eligibility', [DonorPortalController::class, 'checkEligibility'])
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.check-eligibility');
Route::post('/eligibility', [DonorPortalController::class, 'submitEligibility'])
    ->middleware(['donor.active', 'throttle:eligibility-submit'])
    ->name('donor.check-eligibility.submit');
Route::get('/verification', [DonorVerificationController::class, 'index'])
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.verification.index');
Route::post('/verification', [DonorVerificationController::class, 'store'])
    ->middleware(['donor.active', 'throttle:verification-upload'])
    ->name('donor.verification.store');
Route::get('/history', [DonorPortalController::class, 'history'])
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.history');
Route::get('/alerts', [DonorPortalController::class, 'alerts'])
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.alerts');
Route::patch('/notifications/read-all', [DonorPortalController::class, 'markAllNotificationsRead'])
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.notifications.read-all');
Route::patch('/notifications/{notification}/read', [DonorPortalController::class, 'markNotificationRead'])
    ->whereNumber('notification')
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.notifications.read');
Route::get('/blood-requests', [DonorPortalController::class, 'bloodRequests'])
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.blood-requests.index');
Route::get('/blood-requests/{bloodRequest}', [DonorPortalController::class, 'showBloodRequest'])
    ->whereNumber('bloodRequest')
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.blood-requests.show');
Route::post('/blood-requests/{bloodRequest}/interested', [DonorPortalController::class, 'respondBloodRequestInterested'])
    ->whereNumber('bloodRequest')
    ->middleware(['donor.active', 'throttle:blood-request-response'])
    ->name('donor.blood-requests.interested');
Route::post('/blood-requests/{bloodRequest}/decline', [DonorPortalController::class, 'respondBloodRequestDecline'])
    ->whereNumber('bloodRequest')
    ->middleware(['donor.active', 'throttle:blood-request-response'])
    ->name('donor.blood-requests.decline');
Route::post('/profile/complete', [DonorDashboardController::class, 'completeProfile'])
    ->middleware(['donor.active', 'throttle:donor-api'])
    ->name('donor.profile.complete');
Route::post('/logout', [DonorLoginController::class, 'destroy'])->name('donor.logout');

Route::view('/terms-of-service', 'donor.terms')->name('terms');
Route::view('/privacy-policy', 'donor.privacy')->name('privacy');

Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'store'])
    ->middleware('throttle:admin-login')
    ->name('admin.login.store');
Route::post('/admin/auth/google', [AdminAuthController::class, 'googleLogin'])
    ->middleware('throttle:admin-login')
    ->name('admin.login.google');
Route::post('/admin/2fa/challenge', [AdminAuthController::class, 'verifyTwoFactorChallenge'])
    ->middleware('throttle:admin-2fa')
    ->name('admin.2fa.verify');
Route::post('/admin/2fa/challenge/cancel', [AdminAuthController::class, 'cancelTwoFactorChallenge'])->name('admin.2fa.cancel');
Route::get('/admin/forgot-password', [AdminAuthController::class, 'forgotPassword'])->name('admin.password.request');
Route::post('/admin/forgot-password', [AdminAuthController::class, 'sendPasswordResetLink'])
    ->middleware('throttle:password-reset')
    ->name('admin.password.email');
Route::get('/admin/reset-password', [AdminAuthController::class, 'showResetPasswordForm'])->name('admin.password.reset.form');
Route::post('/admin/reset-password', [AdminAuthController::class, 'resetPassword'])
    ->middleware('throttle:password-reset')
    ->name('admin.password.reset');

Route::middleware('admin.auth')->group(function () {
    Route::get('/admin/unauthorized', [AdminAuthController::class, 'unauthorized'])->name('admin.unauthorized');
    Route::post('/admin/logout', [AdminAuthController::class, 'destroy'])->name('admin.logout');
    Route::get('/admin/settings/2fa', [AdminAuthController::class, 'setupTwoFactor'])->name('admin.2fa.setup');
    Route::post('/admin/settings/2fa', [AdminAuthController::class, 'enableTwoFactor'])
        ->middleware('throttle:admin-write')
        ->name('admin.2fa.enable');
    Route::post('/admin/settings/2fa/disable', [AdminAuthController::class, 'disableTwoFactor'])
        ->middleware('throttle:admin-write')
        ->name('admin.2fa.disable');
    Route::middleware('admin.role:admin,staff')->group(function () {
        Route::get('/staff/dashboard', [AdminAuthController::class, 'staffDashboard'])->name('staff.dashboard');
        Route::get('/admin/appointments', [AdminAuthController::class, 'appointments'])->name('admin.appointments');
        Route::get('/admin/appointments/data', [AdminAuthController::class, 'listAppointmentsData'])
            ->middleware('throttle:admin-api')
            ->name('admin.appointments.data');
        Route::patch('/admin/appointments/{appointment}/approve', [AdminAuthController::class, 'approveAppointment'])
            ->whereNumber('appointment')
            ->middleware('throttle:admin-write')
            ->name('admin.appointments.approve');
        Route::patch('/admin/appointments/{appointment}/reject', [AdminAuthController::class, 'rejectAppointment'])
            ->whereNumber('appointment')
            ->middleware('throttle:admin-write')
            ->name('admin.appointments.reject');
        Route::patch('/admin/appointments/{appointment}/check-in', [AdminAuthController::class, 'checkInAppointment'])
            ->whereNumber('appointment')
            ->middleware('throttle:admin-write')
            ->name('admin.appointments.check-in');
        Route::get('/admin/appointments/{appointment}/complete', [AdminAuthController::class, 'completeDonationPage'])
            ->whereNumber('appointment')
            ->middleware('throttle:admin-api')
            ->name('admin.appointments.complete-page');
        Route::patch('/admin/appointments/{appointment}/complete', [AdminAuthController::class, 'completeAppointment'])
            ->whereNumber('appointment')
            ->middleware('throttle:admin-write')
            ->name('admin.appointments.complete');
        Route::patch('/admin/appointments/{appointment}/defer', [AdminAuthController::class, 'deferAppointmentOnSite'])
            ->whereNumber('appointment')
            ->middleware('throttle:admin-write')
            ->name('admin.appointments.defer');
        Route::patch('/admin/appointments/{appointment}/cancel', [AdminAuthController::class, 'cancelAppointment'])
            ->whereNumber('appointment')
            ->middleware('throttle:admin-write')
            ->name('admin.appointments.cancel');
        Route::patch('/admin/appointments/{appointment}/no-show', [AdminAuthController::class, 'markNoShowAppointment'])
            ->whereNumber('appointment')
            ->middleware('throttle:admin-write')
            ->name('admin.appointments.no-show');
        Route::get('/admin/donation-records', [AdminAuthController::class, 'donationRecords'])->name('admin.donation-records');
        Route::get('/admin/donation-records/data', [AdminAuthController::class, 'listDonationRecordsData'])
            ->middleware('throttle:admin-api')
            ->name('admin.donation-records.data');
        Route::get('/admin/blood-availability-mapping', [AdminAuthController::class, 'bloodAvailabilityMapping'])->name('admin.blood-availability-mapping');
        Route::get('/admin/blood-availability/map-data', [AdminAuthController::class, 'mapData'])
            ->middleware('throttle:map-api')
            ->name('admin.map.data');
        Route::get('/admin/blood-availability/facilities', [AdminFacilityController::class, 'mapData'])
            ->middleware('throttle:map-api')
            ->name('admin.facilities.map-data');
        Route::get('/admin/facilities', [AdminFacilityController::class, 'index'])->name('admin.facilities.index');
        Route::get('/admin/facilities/data', [AdminFacilityController::class, 'data'])
            ->middleware('throttle:admin-api')
            ->name('admin.facilities.data');
        Route::get('/admin/facilities/{facility}/inventory', [AdminFacilityController::class, 'inventory'])
            ->whereNumber('facility')
            ->name('admin.facilities.inventory');
        Route::get('/admin/facilities/{facility}/inventory/data', [AdminFacilityController::class, 'inventoryData'])
            ->whereNumber('facility')
            ->middleware('throttle:admin-api')
            ->name('admin.facilities.inventory.data');
        Route::get('/admin/blood-requests', [AdminBloodRequestController::class, 'index'])->name('admin.blood-requests.index');
        Route::get('/admin/blood-requests/data', [AdminBloodRequestController::class, 'data'])
            ->middleware('throttle:admin-api')
            ->name('admin.blood-requests.data');
        Route::post('/admin/blood-requests', [AdminBloodRequestController::class, 'store'])
            ->middleware('throttle:admin-write')
            ->name('admin.blood-requests.store');
        Route::get('/admin/blood-requests/{bloodRequest}', [AdminBloodRequestController::class, 'show'])
            ->whereNumber('bloodRequest')
            ->name('admin.blood-requests.show');
        Route::get('/admin/blood-requests/{bloodRequest}/details', [AdminBloodRequestController::class, 'details'])
            ->whereNumber('bloodRequest')
            ->middleware('throttle:admin-api')
            ->name('admin.blood-requests.details');
        Route::get('/admin/blood-requests/{bloodRequest}/candidates', [AdminBloodRequestController::class, 'candidates'])
            ->whereNumber('bloodRequest')
            ->middleware('throttle:admin-api')
            ->name('admin.blood-requests.candidates');
        Route::post('/admin/blood-requests/{bloodRequest}/notify', [AdminBloodRequestController::class, 'notify'])
            ->whereNumber('bloodRequest')
            ->middleware(['throttle:admin-write', 'throttle:notification-send'])
            ->name('admin.blood-requests.notify');
        Route::patch('/admin/blood-requests/{bloodRequest}/donors/{donor}/status', [AdminBloodRequestController::class, 'updateDonorStatus'])
            ->whereNumber('bloodRequest')
            ->whereNumber('donor')
            ->middleware('throttle:admin-write')
            ->name('admin.blood-requests.donors.status');
        Route::patch('/admin/blood-requests/{bloodRequest}/cancel', [AdminBloodRequestController::class, 'cancel'])
            ->whereNumber('bloodRequest')
            ->middleware('throttle:admin-write')
            ->name('admin.blood-requests.cancel');
        Route::patch('/admin/blood-requests/{bloodRequest}/fulfill', [AdminBloodRequestController::class, 'fulfill'])
            ->whereNumber('bloodRequest')
            ->middleware('throttle:admin-write')
            ->name('admin.blood-requests.fulfill');
        Route::get('/admin/map/donors', [AdminAuthController::class, 'mapDonors'])
            ->middleware('throttle:map-api')
            ->name('admin.map.donors');
        Route::get('/admin/map/barangays', [AdminAuthController::class, 'mapBarangays'])
            ->middleware('throttle:map-api')
            ->name('admin.map.barangays');
        Route::get('/admin/map/summary', [AdminAuthController::class, 'mapSummary'])
            ->middleware('throttle:map-api')
            ->name('admin.map.summary');
        Route::post('/admin/map/geocode-missing', [AdminAuthController::class, 'geocodeMissingLocations'])
            ->middleware(['throttle:admin-write', 'throttle:map-api'])
            ->name('admin.map.geocode-missing');
        Route::get('/admin/notification-center', [AdminNotificationController::class, 'index'])->name('admin.notification-center');
        Route::get('/admin/notifications/data', [AdminNotificationController::class, 'data'])
            ->middleware('throttle:admin-api')
            ->name('admin.notifications.data');
        Route::post('/admin/notifications', [AdminNotificationController::class, 'store'])
            ->middleware(['throttle:admin-write', 'throttle:notification-send'])
            ->name('admin.notifications.store');
        Route::patch('/admin/notifications/read-all', [AdminNotificationController::class, 'markAllRead'])
            ->middleware('throttle:admin-api')
            ->name('admin.notifications.read-all');
        Route::delete('/admin/notifications/clear-all', [AdminNotificationController::class, 'clearAll'])
            ->middleware('throttle:admin-write')
            ->name('admin.notifications.clear-all');
        Route::get('/admin/notifications/{notification}', [AdminNotificationController::class, 'show'])
            ->whereNumber('notification')
            ->middleware('throttle:admin-api')
            ->name('admin.notifications.show');
        Route::patch('/admin/notifications/{notification}/read', [AdminNotificationController::class, 'markRead'])
            ->whereNumber('notification')
            ->middleware('throttle:admin-api')
            ->name('admin.notifications.read');
        Route::delete('/admin/notifications/{notification}', [AdminNotificationController::class, 'destroy'])
            ->whereNumber('notification')
            ->middleware('throttle:admin-write')
            ->name('admin.notifications.destroy');
        Route::get('/admin/audit-logs', [AdminAuthController::class, 'auditLogs'])->name('admin.audit-logs');
        Route::get('/admin/audit-logs/data', [AdminAuthController::class, 'listAuditLogs'])
            ->middleware('throttle:admin-api')
            ->name('admin.audit-logs.data');
        Route::get('/admin/audit-logs/export', [AdminAuthController::class, 'exportAuditLogsCsv'])
            ->middleware('throttle:report-export')
            ->name('admin.audit-logs.export');
        Route::get('/admin/audit-logs/{auditLog}', [AdminAuthController::class, 'showAuditLog'])
            ->whereNumber('auditLog')
            ->middleware('throttle:admin-api')
            ->name('admin.audit-logs.show');
    });

    Route::middleware('admin.role:admin')->group(function () {
        Route::get('/admin/dashboard', [AdminAuthController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/admin/users', [AdminAuthController::class, 'users'])->name('admin.users');
        Route::get('/admin/users/data', [AdminAuthController::class, 'listUsersData'])
            ->middleware('throttle:admin-api')
            ->name('admin.users.data');
        Route::get('/admin/users/export', [AdminAuthController::class, 'exportUsersCsv'])
            ->middleware('throttle:report-export')
            ->name('admin.users.export');
        Route::get('/admin/users/{donor}', [AdminAuthController::class, 'showUser'])
            ->whereNumber('donor')
            ->middleware('throttle:admin-api')
            ->name('admin.users.show');
        Route::put('/admin/users/{donor}', [AdminAuthController::class, 'updateUser'])
            ->whereNumber('donor')
            ->middleware('throttle:admin-write')
            ->name('admin.users.update');
        Route::patch('/admin/users/{donor}/deactivate', [AdminAuthController::class, 'deactivateUser'])
            ->whereNumber('donor')
            ->middleware('throttle:admin-write')
            ->name('admin.users.deactivate');
        Route::get('/admin/donor-verifications', [AdminDonorVerificationController::class, 'index'])->name('admin.donor-verifications.index');
        Route::get('/admin/donor-verifications/{verification}/document', [AdminDonorVerificationController::class, 'document'])
            ->whereNumber('verification')
            ->middleware('throttle:document-access')
            ->name('admin.donor-verifications.document');
        Route::patch('/admin/donor-verifications/{verification}/approve', [AdminDonorVerificationController::class, 'approve'])
            ->whereNumber('verification')
            ->middleware('throttle:admin-write')
            ->name('admin.donor-verifications.approve');
        Route::patch('/admin/donor-verifications/{verification}/reject', [AdminDonorVerificationController::class, 'reject'])
            ->whereNumber('verification')
            ->middleware('throttle:admin-write')
            ->name('admin.donor-verifications.reject');
        Route::get('/admin/donation-events', [AdminDonationEventController::class, 'index'])->name('admin.donation-events.index');
        Route::get('/admin/donation-events/data', [AdminDonationEventController::class, 'data'])
            ->middleware('throttle:admin-api')
            ->name('admin.donation-events.data');
        Route::post('/admin/donation-events', [AdminDonationEventController::class, 'store'])
            ->middleware('throttle:admin-write')
            ->name('admin.donation-events.store');
        Route::post('/admin/facilities', [AdminFacilityController::class, 'store'])
            ->middleware('throttle:admin-write')
            ->name('admin.facilities.store');
        Route::put('/admin/facilities/{facility}', [AdminFacilityController::class, 'update'])
            ->whereNumber('facility')
            ->middleware('throttle:admin-write')
            ->name('admin.facilities.update');
        Route::patch('/admin/facilities/{facility}/status', [AdminFacilityController::class, 'status'])
            ->whereNumber('facility')
            ->middleware('throttle:admin-write')
            ->name('admin.facilities.status');
        Route::put('/admin/facilities/{facility}/inventory', [AdminFacilityController::class, 'updateInventory'])
            ->whereNumber('facility')
            ->middleware(['throttle:admin-write', 'throttle:inventory-update'])
            ->name('admin.facilities.inventory.update');
        Route::patch('/admin/donation-events/{event}/open', [AdminDonationEventController::class, 'open'])
            ->whereNumber('event')
            ->middleware('throttle:admin-write')
            ->name('admin.donation-events.open');
        Route::patch('/admin/donation-events/{event}/close', [AdminDonationEventController::class, 'close'])
            ->whereNumber('event')
            ->middleware('throttle:admin-write')
            ->name('admin.donation-events.close');
        Route::patch('/admin/donation-events/{event}/cancel', [AdminDonationEventController::class, 'cancel'])
            ->whereNumber('event')
            ->middleware('throttle:admin-write')
            ->name('admin.donation-events.cancel');
        Route::patch('/admin/donation-events/{event}/complete', [AdminDonationEventController::class, 'complete'])
            ->whereNumber('event')
            ->middleware('throttle:admin-write')
            ->name('admin.donation-events.complete');
        Route::get('/admin/donation-events/{event}', [AdminDonationEventController::class, 'show'])
            ->whereNumber('event')
            ->middleware('throttle:admin-api')
            ->name('admin.donation-events.show');
        Route::put('/admin/donation-events/{event}', [AdminDonationEventController::class, 'update'])
            ->whereNumber('event')
            ->middleware('throttle:admin-write')
            ->name('admin.donation-events.update');
        Route::delete('/admin/donation-events/{event}', [AdminDonationEventController::class, 'destroy'])
            ->whereNumber('event')
            ->middleware('throttle:admin-write')
            ->name('admin.donation-events.destroy');
        Route::get('/admin/report-analytics', [AdminReportController::class, 'index'])->name('admin.report-analytics');
        Route::get('/admin/report-analytics/data', [AdminReportController::class, 'data'])
            ->middleware('throttle:reports-api')
            ->name('admin.report-analytics.data');
        Route::get('/admin/report-analytics/export', [AdminReportController::class, 'export'])
            ->middleware('throttle:report-export')
            ->name('admin.report-analytics.export');
        Route::get('/admin/profile', [AdminAuthController::class, 'profile'])->name('admin.profile');
        Route::get('/admin/rbac', [AdminAuthController::class, 'rbac'])->name('admin.rbac');
        Route::get('/admin/rbac/users', [AdminAuthController::class, 'listRbacUsers'])
            ->middleware('throttle:admin-api')
            ->name('admin.rbac.users.index');
        Route::post('/admin/rbac/users', [AdminAuthController::class, 'storeRbacUser'])
            ->middleware('throttle:admin-write')
            ->name('admin.rbac.users.store');
        Route::put('/admin/rbac/users/{admin}', [AdminAuthController::class, 'updateRbacUser'])
            ->whereNumber('admin')
            ->middleware('throttle:admin-write')
            ->name('admin.rbac.users.update');
        Route::delete('/admin/rbac/users/{admin}', [AdminAuthController::class, 'deleteRbacUser'])
            ->whereNumber('admin')
            ->middleware('throttle:admin-write')
            ->name('admin.rbac.users.delete');
        Route::patch('/admin/rbac/users/{admin}/password/reset', [AdminAuthController::class, 'resetRbacUserPassword'])
            ->whereNumber('admin')
            ->middleware('throttle:admin-write')
            ->name('admin.rbac.users.password.reset');
        Route::patch('/admin/rbac/users/{admin}/role', [AdminAuthController::class, 'updateRbacUserRole'])
            ->whereNumber('admin')
            ->middleware('throttle:admin-write')
            ->name('admin.rbac.users.role.update');
        Route::get('/admin/settings', [AdminAuthController::class, 'settings'])->name('admin.settings');
        Route::post('/admin/settings/notifications', [AdminAuthController::class, 'updateNotificationSettings'])
            ->middleware('throttle:admin-write')
            ->name('admin.settings.notifications.update');
        Route::post('/admin/settings/general', [AdminAuthController::class, 'updateGeneralSettings'])
            ->middleware('throttle:admin-write')
            ->name('admin.settings.general.update');
        Route::post('/admin/settings/account', [AdminAuthController::class, 'updateAccountSettings'])
            ->middleware('throttle:admin-write')
            ->name('admin.settings.account.update');
        Route::post('/admin/settings/security', [AdminAuthController::class, 'updateSecuritySettings'])
            ->middleware('throttle:admin-write')
            ->name('admin.settings.security.update');

        // Eligibility Management Routes
        Route::prefix('admin/eligibility')->group(function () {
            Route::get('/', [EligibilityController::class, 'index'])->name('admin.eligibility.index');
            Route::get('/data', [EligibilityController::class, 'data'])
                ->middleware('throttle:admin-api')
                ->name('admin.eligibility.data');
            Route::get('/{id}', [EligibilityController::class, 'show'])
                ->whereNumber('id')
                ->middleware('throttle:admin-api')
                ->name('admin.eligibility.show');
            Route::patch('/{id}/review', [EligibilityController::class, 'review'])
                ->whereNumber('id')
                ->middleware('throttle:admin-write')
                ->name('admin.eligibility.review');

            // Question Management Routes
            Route::prefix('questions')->group(function () {
                Route::get('/', [QuestionController::class, 'index'])->name('admin.eligibility.questions.index');
                Route::get('/data', [QuestionController::class, 'data'])
                    ->middleware('throttle:admin-api')
                    ->name('admin.eligibility.questions.data');
                Route::post('/', [QuestionController::class, 'store'])
                    ->middleware('throttle:admin-write')
                    ->name('admin.eligibility.questions.store');
                Route::put('/{id}', [QuestionController::class, 'update'])
                    ->whereNumber('id')
                    ->middleware('throttle:admin-write')
                    ->name('admin.eligibility.questions.update');
                Route::patch('/{id}/toggle', [QuestionController::class, 'toggle'])
                    ->whereNumber('id')
                    ->middleware('throttle:admin-write')
                    ->name('admin.eligibility.questions.toggle');
            });
        });
    });
});

// Webhook for Auto-Deployment

Route::post('/git-deploy-token-734866278', function (Request $request) {
    $secret = (string) config('services.deployment.webhook_secret', '');
    $signature = (string) $request->header('X-Hub-Signature-256', '');
    $expected = $secret !== ''
        ? 'sha256='.hash_hmac('sha256', $request->getContent(), $secret)
        : '';

    if ($secret === '' || $signature === '' || ! hash_equals($expected, $signature)) {
        Log::warning('Rejected unsigned or invalid deployment webhook.', [
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Unauthorized.'], 401);
    }

    Log::info('GitHub Webhook received. Starting deployment.');

    // Ito ang mga command na tatakbo sa server mo
    // Gagamit tayo ng full path para iwas error
    $commands = [
        'git pull origin main',
        'composer install --no-dev --optimize-autoloader',
        'php artisan migrate --force',
        'php artisan optimize',
    ];

    $output = [];
    foreach ($commands as $command) {
        $result = shell_exec('cd '.base_path()." && $command 2>&1");
        $output[] = [
            'command' => $command,
            'successful' => is_string($result) && ! str_contains(strtolower($result), 'error'),
        ];
    }

    Log::info('Deployment finished.', $output);

    return response()->json([
        'message' => 'Deployment completed.',
    ]);
});
