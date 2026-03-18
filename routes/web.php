<?php

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

