# Mailer

Zero Framework ships with a lightweight SMTP mailer that follows Laravel-style configuration while keeping the implementation dependency free. Messages are dispatched through the `Zero\Lib\Mail\Mailer` facade (`Mail`) so you can quickly compose notifications, password reset emails, or system alerts.

## Configuration

Mail settings live in `config/mail.php` and are driven by environment variables. Populate the required keys in your `.env` file:

```ini
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=secret
MAIL_ENCRYPTION=tls # tls, ssl, or leave blank for none
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="Zero Framework"
MAIL_TIMEOUT=30
MAIL_HELO_DOMAIN=my-app.local
MAIL_ALLOW_SELF_SIGNED=false
MAIL_VERIFY_PEER=true
```

Key options:

- `MAIL_MAILER` – only the `smtp` driver is available today.
- `MAIL_HOST` / `MAIL_PORT` – the SMTP endpoint and port (587 for TLS, 465 for SSL).
- `MAIL_ENCRYPTION` – `tls`, `ssl`, or leave empty for plain connections.
- `MAIL_USERNAME` / `MAIL_PASSWORD` – leave blank for unauthenticated relays.
- `MAIL_FROM_*` – default sender information applied to every message.
- `MAIL_TIMEOUT` – socket timeout in seconds (default `30`).
- `MAIL_HELO_DOMAIN` – override the domain used in the `EHLO`/`HELO` handshake.
- `MAIL_ALLOW_SELF_SIGNED` / `MAIL_VERIFY_PEER` – toggle TLS certificate verification behaviour.

## Sending Mail

Use the `Mail` facade (registered in `core/kernel.php`) to compose messages. The callback receives a `Zero\Lib\Mail\Message` instance with fluent helpers for addressing and content.

```php
use Mail;

Mail::send(function ($mail) {
    $mail->to('user@example.com', 'Test User')
         ->subject('Welcome to Zero Framework')
         ->html('<p>Thanks for signing up!</p>');
});
```

### Plain Text

```php
Mail::send(function ($mail) {
    $mail->to('ops@example.com')
         ->subject('Queue is backlogged')
         ->text("Check the workers.\nNothing is being processed.");
});
```

### Reply-To, CC, and BCC

```php
Mail::send(function ($mail) {
    $mail->to('customer@example.com')
         ->cc('support@example.com')
         ->bcc('auditor@example.com')
         ->replyTo('noreply@example.com')
         ->subject('Invoice #2024')
         ->html(view('emails.invoice', ['invoice' => $invoice]));
});
```

### Raw Convenience Helper

For quick notifications you can skip the callback entirely:

```php
Mail::raw('alerts@example.com', 'Deployment finished', 'All services are green.');
```

Set the fourth argument to `true` to treat the body as HTML.

## Error Handling

The mailer throws `Zero\Lib\Mail\MailException` when configuration is missing, the server rejects authentication, or a transport error occurs. Wrap calls in a `try`/`catch` block if you want to surface user-friendly feedback.

```php
try {
    Mail::raw('ops@example.com', 'Heartbeat failed', 'Database is unreachable.');
} catch (Zero\Lib\Mail\MailException $e) {
    logger()->error('Failed to send alert email', ['error' => $e->getMessage()]);
}
```

## Limitations & Future Work

- Only the SMTP driver is implemented; queues and local sendmail integrations are on the roadmap.
- Attachments and multipart/alternative payloads are not yet supported.
- TLS verification defaults to secure settings—loosen them only for local development.

Contributions are welcome! See `todo.md` for potential enhancements.
