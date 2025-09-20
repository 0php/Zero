<?php

namespace App\Controllers\Auth;

use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Session;
use Zero\Lib\Validation\ValidationException;

class EmailVerificationController
{
    public function notice(): Response
    {
        $status = Session::get('status');
        $errors = Session::get('verification_errors') ?? [];
        $old = Session::get('verification_old') ?? [];

        Session::remove('status');
        Session::remove('verification_errors');
        Session::remove('verification_old');

        return view('auth/verify-email', compact('status', 'errors', 'old'));
    }

    public function verify(Request $request, string $token): Response
    {
        try {
            $payload = $request->validate([
                'email' => ['required', 'email'],
            ]);
        } catch (ValidationException $exception) {
            Session::set('status', 'Verification link is missing the email address. Please request a new link.');
            return Response::redirect('/email/verify');
        }

        $email = strtolower((string) $payload['email']);

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            Session::set('status', 'We could not find a user with that email. Please register first.');
            return Response::redirect('/register');
        }

        if ($user->isEmailVerified()) {
            Session::set('status', 'Your email address is already verified. You can sign in now.');
            return Response::redirect('/login');
        }

        $hashed = hash('sha256', $token);

        /** @var EmailVerificationToken|null $record */
        $record = EmailVerificationToken::query()->where('token', $hashed)->first();

        if (! $record instanceof EmailVerificationToken || (int) $record->user_id !== (int) $user->id) {
            Session::set('status', 'That verification link is invalid or has already been used.');
            return Response::redirect('/email/verify');
        }

        if (strtotime((string) $record->expires_at) < time()) {
            $record->delete();
            Session::set('status', 'That verification link has expired. We have emailed you a new one.');
            EmailVerificationService::send($user);
            return Response::redirect('/email/verify');
        }

        $user->markEmailVerified();
        EmailVerificationToken::query()->where('user_id', $user->id)->delete();

        Session::set('status', 'Thanks! Your email has been verified. Please sign in.');

        return Response::redirect('/login');
    }

    public function resend(Request $request): Response
    {
        try {
            $payload = $request->validate([
                'email' => ['required', 'email'],
            ]);
        } catch (ValidationException $exception) {
            $messages = array_map(static fn (array $errors): string => (string) ($errors[0] ?? ''), $exception->errors());

            Session::set('verification_errors', $messages);
            Session::set('verification_old', [
                'email' => $request->input('email'),
            ]);

            return Response::redirect('/email/verify');
        }

        $email = strtolower((string) $payload['email']);

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            Session::set('status', 'If that email is registered we will send a verification message shortly.');
            return Response::redirect('/email/verify');
        }

        if ($user->isEmailVerified()) {
            Session::set('status', 'That email address is already verified. You can sign in now.');
            return Response::redirect('/login');
        }

        EmailVerificationService::send($user);

        Session::set('status', 'We have sent a fresh verification link to your email address.');
        Session::remove('verification_old');

        return Response::redirect('/email/verify');
    }
}
