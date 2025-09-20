<?php

namespace App\Controllers\Auth;

use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Zero\Lib\Crypto;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Session;
use Zero\Lib\Validation\ValidationException;

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
        try {
            $data = $request->validate(
                [
                    'name' => ['required', 'string', 'min:3'],
                    'email' => ['required', 'email', 'unique:users,email'],
                    'password' => ['required', 'string', 'min:8', 'password:letters,numbers', 'confirmed'],
                    'password_confirmation' => ['required', 'string'],
                ],
                [
                    'password.min' => 'Passwords must contain at least :min characters.',
                ],
                [
                    'password' => 'password',
                    'password_confirmation' => 'password confirmation',
                ]
            );
        } catch (ValidationException $exception) {
            $messages = array_map(static fn (array $errors): string => (string) ($errors[0] ?? ''), $exception->errors());

            Session::set('register_errors', $messages);
            Session::set('register_old', [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
            ]);

            return Response::redirect('/register');
        }

        $hashed = Crypto::hash($data['password']);

        $user = User::create([
            'name' => (string) $data['name'],
            'email' => strtolower((string) $data['email']),
            'password' => $hashed,
        ]);

        EmailVerificationService::send($user);

        Session::set('status', 'Account created! Please check your inbox to verify your email before signing in.');

        return Response::redirect('/login');
    }

}
