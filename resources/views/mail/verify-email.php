<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verify your email</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <h1 style="color: #0d6efd;">Confirm your email address</h1>
    <p>Hi {{ $name ?? 'there' }},</p>
    <p>Thanks for creating an account. Please click the button below to verify your email address:</p>
    <p style="margin: 24px 0;">
        <a href="{{ $verificationUrl }}" style="background: #0d6efd; color: #fff; padding: 12px 20px; border-radius: 6px; text-decoration: none;">Verify Email</a>
    </p>
    <p>This link will expire in 60 minutes. If you did not create an account, please disregard this message.</p>
    <p style="margin-top: 32px;">— The Zero Framework Team</p>
</body>
</html>
