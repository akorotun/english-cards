<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LearningProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:5,1')->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/home', HomeController::class)->name('home');
    Route::get('/profiles', [LearningProfileController::class, 'index'])->name('profiles.index');
    Route::post('/profiles', [LearningProfileController::class, 'store'])->name('profiles.store');
    Route::get('/profiles/{profile}', [LearningProfileController::class, 'show'])->whereNumber('profile')->name('profiles.show');
    Route::patch('/profiles/{profile}', [LearningProfileController::class, 'update'])->whereNumber('profile')->name('profiles.update');
    Route::patch('/profiles/{profile}/theme', [LearningProfileController::class, 'updateTheme'])->whereNumber('profile')->name('profiles.theme.update');
    Route::post('/profiles/{profile}/activate', [LearningProfileController::class, 'activate'])->whereNumber('profile')->name('profiles.activate');
});
