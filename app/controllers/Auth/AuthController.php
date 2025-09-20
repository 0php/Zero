<?php

namespace App\Controllers\Auth;

use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Zero\Lib\Auth\Auth;
use Zero\Lib\Crypto;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Session;
use Zero\Lib\Validation\ValidationException;

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
        $errors = Session::get('auth_errors') ?? [];
        $old = Session::get('auth_old') ?? [];

        Session::remove('status');
        Session::remove('auth_errors');
        Session::remove('auth_old');

        return view('auth/login', compact('status', 'errors', 'old'));
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request): Response
    {
        Session::remove('auth_errors');
        Session::remove('auth_old');

        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);
        } catch (ValidationException $exception) {
            $messages = array_map(static fn (array $errors): string => (string) ($errors[0] ?? ''), $exception->errors());

            Session::set('auth_errors', $messages);
            Session::set('auth_old', [
                'email' => $request->input('email'),
            ]);

            return Response::redirect('/login');
        }

        $email = strtolower((string) $credentials['email']);
        $password = (string) $credentials['password'];

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $email)
            ->limit(1)
            ->first();

        if (! $user instanceof User || ! Crypto::validate($password, (string) $user->password)) {
            Session::set('auth_errors', ['email' => 'Invalid credentials provided.']);
            Session::set('auth_old', ['email' => $email]);
            return Response::redirect('/login');
        }

        if (! $user->isEmailVerified()) {
            Session::set('auth_old', ['email' => $email]);
            Session::set('status', 'Please verify your email address before signing in. We have sent you a new verification link.');
            EmailVerificationService::send($user);

            return Response::redirect('/login');
        }

        Auth::login([
            'sub' => $user->id,
            'name' => $user->name ?? $user->email ?? 'User',
            'email' => $user->email ?? null,
        ]);

        Session::remove('auth_old');
        Session::remove('auth_errors');

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
