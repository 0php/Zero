<?php

use App\Controllers\Auth\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\Auth\EmailVerificationController;
use App\Controllers\HomeController;
use App\Controllers\Auth\PasswordResetController;
use App\Controllers\Auth\RegisterController;
use App\Middlewares\Auth as AuthMiddleware;
use App\Middlewares\Guest as GuestMiddleware;
use App\Middlewares\Role as RoleMiddleware;
use Zero\Lib\Router;

Router::get('/', [HomeController::class, 'index'])->name('home');

// Guest-only authentication routes
Router::group(['middleware' => GuestMiddleware::class, 'name' => 'auth'], function () {
    Router::get('/register', [RegisterController::class, 'show'])->name('register.show');
    Router::post('/register', [RegisterController::class, 'store'])->name('register.store');

    Router::get('/login', [AuthController::class, 'showLogin'])->name('login.show');
    Router::post('/login', [AuthController::class, 'login'])->name('login.attempt');

    Router::group(['prefix' => '/password', 'name' => 'password'], function () {
        Router::get('/forgot', [PasswordResetController::class, 'request'])->name('forgot');
        Router::post('/forgot', [PasswordResetController::class, 'email'])->name('email');
        Router::get('/reset/{token}', [PasswordResetController::class, 'show'])->name('reset');
        Router::post('/reset', [PasswordResetController::class, 'update'])->name('update');
    });
});

// Routes that support both guests and authenticated users
Router::group(['prefix' => '/email', 'name' => 'email'], function () {
    Router::get('/verify', [EmailVerificationController::class, 'notice'])->name('verify.notice');
    Router::get('/verify/{token}', [EmailVerificationController::class, 'verify'])->name('verify.process');
    Router::post('/verification-notification', [EmailVerificationController::class, 'resend'])->name('verification.resend');
});

// Authenticated-only routes
Router::group(['middleware' => [AuthMiddleware::class], 'name' => 'auth'], function () {
    Router::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Router::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
