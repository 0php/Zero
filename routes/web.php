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

Router::get('/', [HomeController::class, 'index']);

// Guest-only authentication routes
Router::group(['middleware' => GuestMiddleware::class], function () {
    Router::get('/register', [RegisterController::class, 'show']);
    Router::post('/register', [RegisterController::class, 'store']);

    Router::get('/login', [AuthController::class, 'showLogin']);
    Router::post('/login', [AuthController::class, 'login']);

    Router::group(['prefix' => '/password'], function () {
        Router::get('/forgot', [PasswordResetController::class, 'request']);
        Router::post('/forgot', [PasswordResetController::class, 'email']);
        Router::get('/reset/{token}', [PasswordResetController::class, 'show']);
        Router::post('/reset', [PasswordResetController::class, 'update']);
    });
});

// Routes that support both guests and authenticated users
Router::group(['prefix' => '/email'], function () {
    Router::get('/verify', [EmailVerificationController::class, 'notice']);
    Router::get('/verify/{token}', [EmailVerificationController::class, 'verify']);
    Router::post('/verification-notification', [EmailVerificationController::class, 'resend']);
});

// Authenticated-only routes
Router::group(['middleware' => [AuthMiddleware::class]], function () {
    Router::post('/logout', [AuthController::class, 'logout']);
    Router::get('/dashboard', [DashboardController::class, 'index']);
});

