<?php

namespace App\Services\Auth;

use App\Models\EmailVerificationToken;
use App\Models\User;
use Mail;
use View;

class EmailVerificationService
{
    public static function send(User $user): void
    {
        $plain = bin2hex(random_bytes(32));
        $hashed = hash('sha256', $plain);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->delete();

        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token' => $hashed,
            'expires_at' => $expiresAt,
        ]);

        $appUrl = env('APP_URL', 'http://localhost');
        $verificationUrl = rtrim($appUrl, '/') . '/email/verify/' . $plain . '?email=' . urlencode((string) $user->email);

        $html = View::render('mail/verify-email', [
            'name' => $user->name,
            'verificationUrl' => $verificationUrl,
        ]);

        Mail::send(function ($message) use ($user, $html) {
            $message->to((string) $user->email, $user->name)
                ->subject('Verify your email address')
                ->html($html);
        });
    }
}
