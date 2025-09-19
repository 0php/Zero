<?php

namespace App\Services\Auth;

use App\Models\PasswordResetToken;
use App\Models\User;
use Mail;
use Zero\Lib\Crypto;
use Zero\Lib\View;

class PasswordResetService
{
    public static function sendLink(User $user): void
    {
        $plain = bin2hex(random_bytes(32));
        $hashed = hash('sha256', $plain);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        PasswordResetToken::query()->where('email', $user->email)->delete();

        PasswordResetToken::create([
            'email' => $user->email,
            'token' => $hashed,
            'expires_at' => $expiresAt,
        ]);

        $appUrl = env('APP_URL', 'http://localhost');
        $resetUrl = rtrim($appUrl, '/') . '/password/reset/' . $plain . '?email=' . urlencode((string) $user->email);

        $html = View::render('mail/reset-password', [
            'name' => $user->name,
            'resetUrl' => $resetUrl,
        ]);

        Mail::send(function ($message) use ($user, $html) {
            $message->to((string) $user->email, $user->name)
                ->subject('Reset your password')
                ->html($html);
        });
    }

    public static function resetPassword(User $user, string $newPassword): void
    {
        $user->forceFill([
            'password' => Crypto::hash($newPassword),
        ]);

        $user->save();
    }
}
