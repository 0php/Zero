<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Zero\Lib\Crypto;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Session;

class RegisterController
{
    public function show(): Response
    {
        $status = Session::get('status');
        $errors = Session::get('register_errors') ?? [];
        $old = Session::get('register_old') ?? [];

        Session::remove('status');
        Session::remove('register_errors');
        Session::remove('register_old');

        return view('auth/register', compact('status', 'errors', 'old'));
    }

    public function store(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');

        $errors = $this->validate($name, $email, $password, $passwordConfirmation);

        if (! empty($errors)) {
            Session::set('register_errors', $errors);
            Session::set('register_old', [
                'name' => $name,
                'email' => $email,
            ]);

            return Response::redirect('/register');
        }

        $hashed = Crypto::hash($password);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $hashed,
        ]);

        EmailVerificationService::send($user);

        Session::set('status', 'Account created! Please check your inbox to verify your email before signing in.');

        return Response::redirect('/login');
    }

    private function validate(string $name, string $email, string $password, string $passwordConfirmation): array
    {
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        } elseif (User::query()->where('email', $email)->exists()) {
            $errors['email'] = 'That email address is already registered.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }

        return $errors;
    }
}
