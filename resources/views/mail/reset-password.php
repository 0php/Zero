<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset your password</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <h1 style="color: #0d6efd;">Reset your password</h1>
    <p>Hi {{ $name ?? 'there' }},</p>
    <p>We received a request to reset the password for your account. Click the button below to choose a new password:</p>
    <p style="margin: 24px 0;">
        <a href="{{ $resetUrl }}" style="background: #0d6efd; color: #fff; padding: 12px 20px; border-radius: 6px; text-decoration: none;">Reset Password</a>
    </p>
    <p>If you did not request a password reset, you can safely ignore this email.</p>
    <p style="margin-top: 32px;">— The Zero Framework Team</p>
</body>
</html>
