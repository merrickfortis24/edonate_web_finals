<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\Admin\DonorVerificationController as AdminDonorVerificationController;
use App\Http\Controllers\Admin\DonationEventController as AdminDonationEventController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\DonorLoginController;
use App\Http\Controllers\DonorDashboardController;
use App\Http\Controllers\DonorPortalController;
use App\Http\Controllers\DonorSignupController;
use App\Http\Controllers\DonorVerificationController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\EligibilityController;
use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('/signup', [DonorSignupController::class, 'create'])->name('donor.signup');
Route::post('/signup', [DonorSignupController::class, 'store'])->name('donor.signup.store');
Route::get('/signup/check-email', [DonorSignupController::class, 'checkEmail'])->name('donor.signup.check-email');
Route::post('/signup/send-otp', [DonorSignupController::class, 'sendOtp'])->name('donor.signup.send-otp');
Route::post('/signup/confirm-otp', [DonorSignupController::class, 'confirmOtp'])->name('donor.signup.confirm-otp');

Route::get('/login', [DonorLoginController::class, 'create'])->name('donor.login');
Route::post('/login', [DonorLoginController::class, 'store'])->name('donor.login.store');
Route::post('/auth/google', [SocialAuthController::class, 'handleGoogleLogin'])->name('auth.google');
Route::get('/dashboard', [DonorDashboardController::class, 'index'])->name('donor.dashboard');
Route::get('/appointments/book', [DonorPortalController::class, 'bookAppointment'])->name('donor.book-appointment');
Route::get('/appointments/events', [DonorPortalController::class, 'availableEvents'])->name('donor.appointments.events');
Route::post('/appointments/book', [DonorPortalController::class, 'storeAppointment'])->name('donor.book-appointment.store');
Route::patch('/appointments/{appointment}/cancel', [DonorPortalController::class, 'cancelAppointment'])
    ->whereNumber('appointment')
    ->name('donor.appointments.cancel');
Route::get('/eligibility', [DonorPortalController::class, 'checkEligibility'])->name('donor.check-eligibility');
Route::post('/eligibility', [DonorPortalController::class, 'submitEligibility'])->name('donor.check-eligibility.submit');
Route::get('/verification', [DonorVerificationController::class, 'index'])->name('donor.verification.index');
Route::post('/verification', [DonorVerificationController::class, 'store'])->name('donor.verification.store');
Route::get('/history', [DonorPortalController::class, 'history'])->name('donor.history');
Route::get('/alerts', [DonorPortalController::class, 'alerts'])->name('donor.alerts');
Route::post('/profile/complete', [DonorDashboardController::class, 'completeProfile'])->name('donor.profile.complete');
Route::post('/logout', [DonorLoginController::class, 'destroy'])->name('donor.logout');

Route::view('/terms-of-service', 'donor.terms')->name('terms');
Route::view('/privacy-policy', 'donor.privacy')->name('privacy');

Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'store'])->name('admin.login.store');
Route::post('/admin/2fa/challenge', [AdminAuthController::class, 'verifyTwoFactorChallenge'])->name('admin.2fa.verify');
Route::post('/admin/2fa/challenge/cancel', [AdminAuthController::class, 'cancelTwoFactorChallenge'])->name('admin.2fa.cancel');
Route::get('/admin/forgot-password', [AdminAuthController::class, 'forgotPassword'])->name('admin.password.request');
Route::post('/admin/forgot-password', [AdminAuthController::class, 'sendPasswordResetLink'])->name('admin.password.email');
Route::get('/admin/reset-password', [AdminAuthController::class, 'showResetPasswordForm'])->name('admin.password.reset.form');
Route::post('/admin/reset-password', [AdminAuthController::class, 'resetPassword'])->name('admin.password.reset');

Route::middleware('admin.auth')->group(function () {
    Route::get('/admin/unauthorized', [AdminAuthController::class, 'unauthorized'])->name('admin.unauthorized');
    Route::post('/admin/logout', [AdminAuthController::class, 'destroy'])->name('admin.logout');
    Route::get('/admin/settings/2fa', [AdminAuthController::class, 'setupTwoFactor'])->name('admin.2fa.setup');
    Route::post('/admin/settings/2fa', [AdminAuthController::class, 'enableTwoFactor'])->name('admin.2fa.enable');
    Route::post('/admin/settings/2fa/disable', [AdminAuthController::class, 'disableTwoFactor'])->name('admin.2fa.disable');

    Route::middleware('admin.role:admin,staff')->group(function () {
        Route::get('/staff/dashboard', [AdminAuthController::class, 'staffDashboard'])->name('staff.dashboard');
        Route::get('/admin/appointments', [AdminAuthController::class, 'appointments'])->name('admin.appointments');
        Route::get('/admin/appointments/data', [AdminAuthController::class, 'listAppointmentsData'])->name('admin.appointments.data');
        Route::patch('/admin/appointments/{appointment}/approve', [AdminAuthController::class, 'approveAppointment'])
            ->whereNumber('appointment')
            ->name('admin.appointments.approve');
        Route::patch('/admin/appointments/{appointment}/reject', [AdminAuthController::class, 'rejectAppointment'])
            ->whereNumber('appointment')
            ->name('admin.appointments.reject');
        Route::patch('/admin/appointments/{appointment}/reschedule', [AdminAuthController::class, 'rescheduleAppointment'])
            ->whereNumber('appointment')
            ->name('admin.appointments.reschedule');
        Route::patch('/admin/appointments/{appointment}/check-in', [AdminAuthController::class, 'checkInAppointment'])
            ->whereNumber('appointment')
            ->name('admin.appointments.check-in');
        Route::patch('/admin/appointments/{appointment}/complete', [AdminAuthController::class, 'completeAppointment'])
            ->whereNumber('appointment')
            ->name('admin.appointments.complete');
        Route::patch('/admin/appointments/{appointment}/defer', [AdminAuthController::class, 'deferAppointmentOnSite'])
            ->whereNumber('appointment')
            ->name('admin.appointments.defer');
        Route::patch('/admin/appointments/{appointment}/cancel', [AdminAuthController::class, 'cancelAppointment'])
            ->whereNumber('appointment')
            ->name('admin.appointments.cancel');
        Route::patch('/admin/appointments/{appointment}/no-show', [AdminAuthController::class, 'markNoShowAppointment'])
            ->whereNumber('appointment')
            ->name('admin.appointments.no-show');
        Route::get('/admin/donation-records', [AdminAuthController::class, 'donationRecords'])->name('admin.donation-records');
        Route::get('/admin/donation-records/data', [AdminAuthController::class, 'listDonationRecordsData'])->name('admin.donation-records.data');
        Route::get('/admin/blood-availability-mapping', [AdminAuthController::class, 'bloodAvailabilityMapping'])->name('admin.blood-availability-mapping');
        Route::get('/admin/map/donors', [AdminAuthController::class, 'mapDonors'])->name('admin.map.donors');
        Route::get('/admin/map/barangays', [AdminAuthController::class, 'mapBarangays'])->name('admin.map.barangays');
        Route::get('/admin/map/summary', [AdminAuthController::class, 'mapSummary'])->name('admin.map.summary');
        Route::post('/admin/map/geocode-missing', [AdminAuthController::class, 'geocodeMissingLocations'])->name('admin.map.geocode-missing');
        Route::get('/admin/notification-center', [AdminNotificationController::class, 'index'])->name('admin.notification-center');
        Route::get('/admin/notifications/data', [AdminNotificationController::class, 'data'])->name('admin.notifications.data');
        Route::post('/admin/notifications', [AdminNotificationController::class, 'store'])->name('admin.notifications.store');
        Route::patch('/admin/notifications/read-all', [AdminNotificationController::class, 'markAllRead'])->name('admin.notifications.read-all');
        Route::delete('/admin/notifications/clear-all', [AdminNotificationController::class, 'clearAll'])->name('admin.notifications.clear-all');
        Route::get('/admin/notifications/{notification}', [AdminNotificationController::class, 'show'])
            ->whereNumber('notification')
            ->name('admin.notifications.show');
        Route::patch('/admin/notifications/{notification}/read', [AdminNotificationController::class, 'markRead'])
            ->whereNumber('notification')
            ->name('admin.notifications.read');
        Route::delete('/admin/notifications/{notification}', [AdminNotificationController::class, 'destroy'])
            ->whereNumber('notification')
            ->name('admin.notifications.destroy');
        Route::get('/admin/audit-logs', [AdminAuthController::class, 'auditLogs'])->name('admin.audit-logs');
        Route::get('/admin/audit-logs/data', [AdminAuthController::class, 'listAuditLogs'])->name('admin.audit-logs.data');
        Route::get('/admin/audit-logs/export', [AdminAuthController::class, 'exportAuditLogsCsv'])->name('admin.audit-logs.export');
    });

    Route::middleware('admin.role:admin')->group(function () {
        Route::get('/admin/dashboard', [AdminAuthController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/admin/users', [AdminAuthController::class, 'users'])->name('admin.users');
        Route::get('/admin/users/data', [AdminAuthController::class, 'listUsersData'])->name('admin.users.data');
        Route::get('/admin/users/{donor}', [AdminAuthController::class, 'showUser'])
            ->whereNumber('donor')
            ->name('admin.users.show');
        Route::put('/admin/users/{donor}', [AdminAuthController::class, 'updateUser'])
            ->whereNumber('donor')
            ->name('admin.users.update');
        Route::delete('/admin/users/{donor}', [AdminAuthController::class, 'deleteUser'])
            ->whereNumber('donor')
            ->name('admin.users.delete');
        Route::get('/admin/donor-verifications', [AdminDonorVerificationController::class, 'index'])->name('admin.donor-verifications.index');
        Route::get('/admin/donor-verifications/{verification}/document', [AdminDonorVerificationController::class, 'document'])
            ->whereNumber('verification')
            ->name('admin.donor-verifications.document');
        Route::patch('/admin/donor-verifications/{verification}/approve', [AdminDonorVerificationController::class, 'approve'])
            ->whereNumber('verification')
            ->name('admin.donor-verifications.approve');
        Route::patch('/admin/donor-verifications/{verification}/reject', [AdminDonorVerificationController::class, 'reject'])
            ->whereNumber('verification')
            ->name('admin.donor-verifications.reject');
        Route::get('/admin/donation-events', [AdminDonationEventController::class, 'index'])->name('admin.donation-events.index');
        Route::get('/admin/donation-events/data', [AdminDonationEventController::class, 'data'])->name('admin.donation-events.data');
        Route::post('/admin/donation-events', [AdminDonationEventController::class, 'store'])->name('admin.donation-events.store');
        Route::patch('/admin/donation-events/{event}/open', [AdminDonationEventController::class, 'open'])
            ->whereNumber('event')
            ->name('admin.donation-events.open');
        Route::patch('/admin/donation-events/{event}/close', [AdminDonationEventController::class, 'close'])
            ->whereNumber('event')
            ->name('admin.donation-events.close');
        Route::patch('/admin/donation-events/{event}/cancel', [AdminDonationEventController::class, 'cancel'])
            ->whereNumber('event')
            ->name('admin.donation-events.cancel');
        Route::patch('/admin/donation-events/{event}/complete', [AdminDonationEventController::class, 'complete'])
            ->whereNumber('event')
            ->name('admin.donation-events.complete');
        Route::get('/admin/donation-events/{event}', [AdminDonationEventController::class, 'show'])
            ->whereNumber('event')
            ->name('admin.donation-events.show');
        Route::put('/admin/donation-events/{event}', [AdminDonationEventController::class, 'update'])
            ->whereNumber('event')
            ->name('admin.donation-events.update');
        Route::delete('/admin/donation-events/{event}', [AdminDonationEventController::class, 'destroy'])
            ->whereNumber('event')
            ->name('admin.donation-events.destroy');
        Route::get('/admin/report-analytics', [AdminAuthController::class, 'reportAnalytics'])->name('admin.report-analytics');
        Route::get('/admin/rbac', [AdminAuthController::class, 'rbac'])->name('admin.rbac');
        Route::get('/admin/rbac/users', [AdminAuthController::class, 'listRbacUsers'])
            ->name('admin.rbac.users.index');
        Route::post('/admin/rbac/users', [AdminAuthController::class, 'storeRbacUser'])
            ->name('admin.rbac.users.store');
        Route::put('/admin/rbac/users/{admin}', [AdminAuthController::class, 'updateRbacUser'])
            ->whereNumber('admin')
            ->name('admin.rbac.users.update');
        Route::delete('/admin/rbac/users/{admin}', [AdminAuthController::class, 'deleteRbacUser'])
            ->whereNumber('admin')
            ->name('admin.rbac.users.delete');
        Route::patch('/admin/rbac/users/{admin}/password/reset', [AdminAuthController::class, 'resetRbacUserPassword'])
            ->whereNumber('admin')
            ->name('admin.rbac.users.password.reset');
        Route::patch('/admin/rbac/users/{admin}/role', [AdminAuthController::class, 'updateRbacUserRole'])
            ->whereNumber('admin')
            ->name('admin.rbac.users.role.update');
        Route::get('/admin/settings', [AdminAuthController::class, 'settings'])->name('admin.settings');
        Route::post('/admin/settings/security', [AdminAuthController::class, 'updateSecuritySettings'])
            ->name('admin.settings.security.update');

        // Eligibility Management Routes
        Route::prefix('admin/eligibility')->group(function () {
            Route::get('/', [EligibilityController::class, 'index'])->name('admin.eligibility.index');
            Route::get('/data', [EligibilityController::class, 'data'])->name('admin.eligibility.data');
            Route::get('/{id}', [EligibilityController::class, 'show'])->whereNumber('id')->name('admin.eligibility.show');
            Route::patch('/{id}/review', [EligibilityController::class, 'review'])->whereNumber('id')->name('admin.eligibility.review');

            // Question Management Routes
            Route::prefix('questions')->group(function () {
                Route::get('/', [QuestionController::class, 'index'])->name('admin.eligibility.questions.index');
                Route::get('/data', [QuestionController::class, 'data'])->name('admin.eligibility.questions.data');
                Route::post('/', [QuestionController::class, 'store'])->name('admin.eligibility.questions.store');
                Route::put('/{id}', [QuestionController::class, 'update'])->whereNumber('id')->name('admin.eligibility.questions.update');
                Route::patch('/{id}/toggle', [QuestionController::class, 'toggle'])->whereNumber('id')->name('admin.eligibility.questions.toggle');
            });
        });
    });
});

// Webhook for Auto-Deployment
use Illuminate\Support\Facades\Process;

Route::post('/git-deploy-token-734866278', function () {
    // Security: I-check kung galing talaga kay GitHub ang request (Optional but good)
    
    Log::info('GitHub Webhook received. Starting deployment...');

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
        // Tatakbo ang command sa root folder ng project mo
        $result = shell_exec("cd " . base_path() . " && $command 2>&1");
        $output[] = $command . ": " . $result;
    }

    Log::info('Deployment finished.', $output);

    return response()->json([
        'message' => 'Deployment successful',
        'output' => $output
    ]);
});
