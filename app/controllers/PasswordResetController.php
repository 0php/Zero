<?php

namespace App\Controllers;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\Auth\PasswordResetService;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Session;

class PasswordResetController
{
    public function request(): Response
    {
        $status = Session::get('status');
        $errors = Session::get('password_reset_errors') ?? [];

        Session::remove('status');
        Session::remove('password_reset_errors');

        return view('auth/forgot-password', compact('status', 'errors'));
    }

    public function email(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::set('password_reset_errors', ['email' => 'Enter a valid email address.']);
            return Response::redirect('/password/forgot');
        }

        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            PasswordResetService::sendLink($user);
        }

        Session::set('status', 'If that email exists in our system, we have sent a password reset link.');

        return Response::redirect('/password/forgot');
    }

    public function show(Request $request, string $token): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));

        if ($email === '') {
            Session::set('status', 'Password reset link is missing the email address. Please request a new link.');
            return Response::redirect('/password/forgot');
        }

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
        $token = (string) $request->input('token', '');
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');

        $errors = [];

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }

        if (! empty($errors)) {
            Session::set('password_reset_errors', $errors);
            return Response::redirect('/password/reset/' . $token . '?email=' . urlencode($email));
        }

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
