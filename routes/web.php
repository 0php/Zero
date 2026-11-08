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
use App\Middlewares\Throttle;
use App\Middlewares\VerifyCsrfToken;
use Zero\Lib\Router;

Router::get('/', [HomeController::class, 'index'])->name('home');

// Guest-only authentication routes
Router::group(['middleware' => [GuestMiddleware::class, VerifyCsrfToken::class], 'name' => 'auth'], function () {
    Router::get('/register', [RegisterController::class, 'show'])->name('register.show');
    Router::post('/register', [RegisterController::class, 'store'], [Throttle::class, 5, 300, 'ip_route'])->name('register.store');

    Router::get('/login', [AuthController::class, 'showLogin'])->name('login.show');
    Router::post('/login', [AuthController::class, 'login'], [Throttle::class, 5, 60, 'ip_route'])->name('login.attempt');

    Router::group(['prefix' => '/password', 'name' => 'password'], function () {
        Router::get('/forgot', [PasswordResetController::class, 'request'])->name('forgot');
        Router::post('/forgot', [PasswordResetController::class, 'email'], [Throttle::class, 3, 300, 'ip_route'])->name('email');
        Router::get('/reset/{token}', [PasswordResetController::class, 'show'])->name('reset');
        Router::post('/reset', [PasswordResetController::class, 'update'], [Throttle::class, 5, 300, 'ip_route'])->name('update');
    });
});

// Routes that support both guests and authenticated users
Router::group(['prefix' => '/email', 'name' => 'email', 'middleware' => VerifyCsrfToken::class], function () {
    Router::get('/verify', [EmailVerificationController::class, 'notice'])->name('verify.notice');
    Router::get('/verify/{token}', [EmailVerificationController::class, 'verify'])->name('verify.process');
    Router::post('/verification-notification', [EmailVerificationController::class, 'resend'], [Throttle::class, 3, 300, 'ip_route'])->name('verification.resend');
});

// Authenticated-only routes
Router::group(['middleware' => [AuthMiddleware::class, VerifyCsrfToken::class], 'name' => 'auth'], function () {
    Router::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Router::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
