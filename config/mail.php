<?php

$encryption = env('MAIL_ENCRYPTION', 'tls');
if (is_string($encryption)) {
    $encryption = strtolower(trim($encryption));
    if ($encryption === '' || $encryption === 'null' || $encryption === 'none') {
        $encryption = null;
    }
}

if ($encryption !== null && ! in_array($encryption, ['ssl', 'tls'], true)) {
    $encryption = null;
}

return [
    'default' => env('MAIL_MAILER', 'smtp'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Zero Framework'),
    ],

    'smtp' => [
        'host' => env('MAIL_HOST', '127.0.0.1'),
        'port' => (int) env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME', null) ?: null,
        'password' => env('MAIL_PASSWORD', null) ?: null,
        'encryption' => $encryption, // tls, ssl, or null
        'timeout' => (int) env('MAIL_TIMEOUT', 30),
        'hello' => env('MAIL_HELO_DOMAIN', null) ?: null,
        'allow_self_signed' => filter_var(env('MAIL_ALLOW_SELF_SIGNED', false), FILTER_VALIDATE_BOOL),
        'verify_peer' => filter_var(env('MAIL_VERIFY_PEER', true), FILTER_VALIDATE_BOOL),
    ],
];
