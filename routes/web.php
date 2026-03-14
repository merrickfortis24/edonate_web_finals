<?php

use App\Http\Controllers\DonorSignupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('signup');
});

Route::get('/signup', [DonorSignupController::class, 'create'])->name('donor.signup');
Route::post('/signup', [DonorSignupController::class, 'store'])->name('donor.signup.store');

Route::view('/terms-of-service', 'terms')->name('terms');
Route::view('/privacy-policy', 'privacy')->name('privacy');
