<?php

use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

// This browser widget uses Laravel's session and CSRF cookie at /api/chat.
Route::middleware('web')->group(function (): void {
    Route::get('/chat', [ChatbotController::class, 'history'])
        ->middleware('throttle:public-api')
        ->name('chat.history');

    Route::post('/chat', [ChatbotController::class, 'store'])
        ->middleware(['admin.auth', 'throttle:chatbot'])
        ->name('chat.store');
    // Allow erasure of this browser's conversation even after optional consent is withdrawn.
    Route::delete('/chat', [ChatbotController::class, 'destroy'])
        ->middleware('throttle:public-api')->name('chat.destroy');
});
