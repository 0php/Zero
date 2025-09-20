<?php

namespace App\Controllers\Auth;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\Auth\PasswordResetService;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Session;
use Zero\Lib\Validation\ValidationException;

class PasswordResetController
{
    public function request(): Response
    {
        $status = Session::get('status');
        $errors = Session::get('password_reset_errors') ?? [];
        $old = Session::get('password_reset_old') ?? [];

        Session::remove('status');
        Session::remove('password_reset_errors');
        Session::remove('password_reset_old');

        return view('auth/forgot-password', compact('status', 'errors', 'old'));
    }

    public function email(Request $request): Response
    {
        try {
            $payload = $request->validate([
                'email' => ['required', 'email'],
            ]);
        } catch (ValidationException $exception) {
            $messages = array_map(static fn (array $errors): string => (string) ($errors[0] ?? ''), $exception->errors());

            Session::set('password_reset_errors', $messages);
            Session::set('password_reset_old', [
                'email' => $request->input('email'),
            ]);

            return Response::redirect('/password/forgot');
        }

        $email = strtolower((string) $payload['email']);

        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            PasswordResetService::sendLink($user);
        }

        Session::set('password_reset_old', ['email' => $email]);
        Session::set('status', 'If that email exists in our system, we have sent a password reset link.');

        return Response::redirect('/password/forgot');
    }

    public function show(Request $request, string $token): Response
    {
        try {
            $payload = $request->validate([
                'email' => ['required', 'email'],
            ]);
        } catch (ValidationException $exception) {
            Session::set('status', 'Password reset link is missing the email address. Please request a new link.');
            return Response::redirect('/password/forgot');
        }

        $email = strtolower((string) $payload['email']);

        if (! $this->tokenIsValid($email, $token)) {
            Session::set('status', 'That password reset link is invalid or expired. Please request a new one.');
            return Response::redirect('/password/forgot');
        }

        $errors = Session::get('password_reset_errors') ?? [];
        Session::remove('password_reset_errors');

        return view('auth/reset-password', [
            'token' => $token,
            'email' => $email,
            'errors' => $errors,
        ]);
    }

    public function update(Request $request): Response
    {
        try {
            $payload = $request->validate(
                [
                    'token' => ['required', 'string'],
                    'email' => ['required', 'email'],
                    'password' => ['required', 'string', 'min:8', 'password:letters,numbers', 'confirmed'],
                    'password_confirmation' => ['required', 'string'],
                ],
                [
                    'password.min' => 'Passwords must contain at least :min characters.',
                ]
            );
        } catch (ValidationException $exception) {
            $messages = array_map(static fn (array $errors): string => (string) ($errors[0] ?? ''), $exception->errors());

            Session::set('password_reset_errors', $messages);

            $token = (string) $request->input('token', '');
            $email = (string) $request->input('email', '');

            return Response::redirect('/password/reset/' . $token . '?email=' . urlencode($email));
        }

        $token = (string) $payload['token'];
        $email = strtolower((string) $payload['email']);
        $password = (string) $payload['password'];

        if (! $this->tokenIsValid($email, $token)) {
            Session::set('status', 'That password reset link is invalid or expired. Please request a new one.');
            return Response::redirect('/password/forgot');
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            Session::set('status', 'We could not find an account for that email address.');
            return Response::redirect('/register');
        }

        PasswordResetService::resetPassword($user, $password);
        PasswordResetToken::query()->where('email', $email)->delete();

        Session::set('status', 'Your password has been updated. Please sign in.');

        return Response::redirect('/login');
    }

    private function tokenIsValid(string $email, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $hashed = hash('sha256', $token);

        /** @var PasswordResetToken|null $record */
        $record = PasswordResetToken::query()
            ->where('email', $email)
            ->where('token', $hashed)
            ->first();

        if (! $record instanceof PasswordResetToken) {
            return false;
        }

        if (strtotime((string) $record->expires_at) < time()) {
            $record->delete();

            return false;
        }

        return true;
    }
}
