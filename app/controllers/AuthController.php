<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Zero\Lib\Auth\Auth;
use Zero\Lib\Crypto;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Session;

class AuthController
{
    /**
     * Display the login form.
     */
    public function showLogin(): Response
    {
        
        if (Auth::user()) {
            return Response::redirect('/');
        }


        $status = Session::get('status');
        $error = Session::get('auth_error');
        $email = Session::get('auth_email');

        Session::remove('status');
        Session::remove('auth_error');
        Session::remove('auth_email');

        return view('auth/login', compact('status', 'error', 'email'));
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');

        Session::remove('auth_error');
        Session::remove('auth_email');

        if ($email === '' || $password === '') {
            Session::set('auth_error', 'Email and password are required.');
            Session::set('auth_email', $email);
            return Response::redirect('/login');
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $email)
            ->limit(1)
            ->first();

        if (! $user instanceof User || ! Crypto::validate($password, (string) $user->password)) {
            Session::set('auth_error', 'Invalid credentials provided.');
            Session::set('auth_email', $email);
            return Response::redirect('/login');
        }

        if (! $user->isEmailVerified()) {
            Session::set('auth_email', $email);
            Session::set('status', 'Please verify your email address before signing in. We have sent you a new verification link.');
            EmailVerificationService::send($user);

            return Response::redirect('/login');
        }

        Auth::login([
            'sub' => $user->id,
            'name' => $user->name ?? $user->email ?? 'User',
            'email' => $user->email ?? null,
        ]);

        $intended = Session::get('auth_redirect');
        Session::remove('auth_redirect');

        return Response::redirect($intended ?: '/');
    }

    /**
     * Destroy the authenticated session.
     */
    public function logout(): Response
    {
        Auth::logout();
        Session::remove('auth_redirect');

        return Response::redirect('/login');
    }
}
