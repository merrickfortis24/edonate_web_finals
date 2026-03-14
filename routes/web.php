<?php

use App\Http\Controllers\DonorLoginController;
use App\Http\Controllers\DonorSignupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('signup');
});

Route::get('/signup', [DonorSignupController::class, 'create'])->name('donor.signup');
Route::post('/signup', [DonorSignupController::class, 'store'])->name('donor.signup.store');
Route::post('/signup/send-otp', [DonorSignupController::class, 'sendOtp'])->name('donor.signup.send-otp');
Route::post('/signup/confirm-otp', [DonorSignupController::class, 'confirmOtp'])->name('donor.signup.confirm-otp');

Route::get('/login', [DonorLoginController::class, 'create'])->name('donor.login');
Route::post('/login', [DonorLoginController::class, 'store'])->name('donor.login.store');
Route::post('/logout', [DonorLoginController::class, 'destroy'])->name('donor.logout');

Route::view('/terms-of-service', 'terms')->name('terms');
Route::view('/privacy-policy', 'privacy')->name('privacy');
