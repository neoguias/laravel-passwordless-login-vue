<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'send'])->middleware('throttle:10,1')->name('login.send');
    Route::get('/login/codigo', [LoginController::class, 'code'])->name('login.code');
    Route::post('/login/codigo', [LoginController::class, 'verify'])->middleware('throttle:30,1')->name('login.verify');
    Route::post('/login/codigo/reenviar', [LoginController::class, 'resend'])->middleware('throttle:6,1')->name('login.resend');
});

Route::middleware('auth')->group(function () {
    Route::inertia('/panel', 'Dashboard')->name('dashboard');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
