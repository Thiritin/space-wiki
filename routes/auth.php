<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', function () {
        return inertia('auth/Login');
    })->name('login');

    Route::get('auth/login', [AuthController::class, 'login'])
        ->name('auth.login');

    Route::get('auth/callback', [AuthController::class, 'loginCallback'])
        ->name('auth.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])
        ->name('logout');
});
