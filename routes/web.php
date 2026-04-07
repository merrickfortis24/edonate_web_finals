<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\DonorLoginController;
use App\Http\Controllers\DonorDashboardController;
use App\Http\Controllers\DonorPortalController;
use App\Http\Controllers\DonorSignupController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('donor.signup');
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
Route::post('/appointments/book', [DonorPortalController::class, 'storeAppointment'])->name('donor.book-appointment.store');
Route::get('/eligibility', [DonorPortalController::class, 'checkEligibility'])->name('donor.check-eligibility');
Route::get('/history', [DonorPortalController::class, 'history'])->name('donor.history');
Route::get('/alerts', [DonorPortalController::class, 'alerts'])->name('donor.alerts');
Route::post('/profile/complete', [DonorDashboardController::class, 'completeProfile'])->name('donor.profile.complete');
Route::post('/logout', [DonorLoginController::class, 'destroy'])->name('donor.logout');

Route::view('/terms-of-service', 'donor.terms')->name('terms');
Route::view('/privacy-policy', 'donor.privacy')->name('privacy');
Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'store'])->name('admin.login.store');
Route::get('/admin/forgot-password', [AdminAuthController::class, 'forgotPassword'])->name('admin.password.request');
Route::post('/admin/forgot-password', [AdminAuthController::class, 'sendPasswordResetLink'])->name('admin.password.email');
Route::get('/admin/reset-password', [AdminAuthController::class, 'showResetPasswordForm'])->name('admin.password.reset.form');
Route::post('/admin/reset-password', [AdminAuthController::class, 'resetPassword'])->name('admin.password.reset');
Route::get('/admin/dashboard', [AdminAuthController::class, 'dashboard'])->name('admin.dashboard');
Route::get('/admin/users', [AdminAuthController::class, 'users'])->name('admin.users');
Route::get('/admin/appointments', [AdminAuthController::class, 'appointments'])->name('admin.appointments');
Route::get('/admin/donation-records', [AdminAuthController::class, 'donationRecords'])->name('admin.donation-records');
Route::get('/admin/blood-availability-mapping', [AdminAuthController::class, 'bloodAvailabilityMapping'])->name('admin.blood-availability-mapping');
Route::get('/admin/notification-center', [AdminAuthController::class, 'notificationCenter'])->name('admin.notification-center');
Route::get('/admin/report-analytics', [AdminAuthController::class, 'reportAnalytics'])->name('admin.report-analytics');
Route::get('/admin/audit-logs', [AdminAuthController::class, 'auditLogs'])->name('admin.audit-logs');
Route::get('/admin/rbac', [AdminAuthController::class, 'rbac'])->name('admin.rbac');
Route::get('/admin/settings', [AdminAuthController::class, 'settings'])->name('admin.settings');
Route::post('/admin/logout', [AdminAuthController::class, 'destroy'])->name('admin.logout');

