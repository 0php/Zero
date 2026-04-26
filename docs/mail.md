# Mailer

Zero Framework ships with a lightweight SMTP mailer that follows Laravel-style configuration while keeping the implementation dependency free. Messages are dispatched through the `Zero\Lib\Mail\Mailer` facade (`Mail`).

Implementation: [`Mailer.php`](../core/libraries/Mail/Mailer.php), [`Message.php`](../core/libraries/Mail/Message.php), [`Transport/SmtpTransport.php`](../core/libraries/Mail/Transport/SmtpTransport.php).

---

## Mailer API

```php
use Zero\Lib\Mail\Mailer;
// or via the kernel alias:
use Mail;
```

### `Mailer::send(Closure $callback): void`
Build and send a message inside a closure. The closure receives a fresh `Message` (with the configured default `from` already applied).
```php
Mail::send(function ($message) {
    $message
        ->to('user@example.test', 'Tofik')
        ->subject('Welcome!')
        ->html(view('mail.welcome', ['name' => 'Tofik']));
});
```

### `Mailer::raw(string $to, string $subject, string $body, bool $isHtml = false): void`
Shortcut for one-line emails with no extra headers/attachments.
```php
Mail::raw('ops@example.test', 'Heartbeat', 'still alive');
Mail::raw('user@example.test', 'Hi', '<b>hello</b>', isHtml: true);
```

### `Mailer::instance(): self`
Get the underlying singleton. Rarely needed — `send()` and `raw()` cover the common cases.
```php
$mailer = Mailer::instance();
```

### `Mailer::reset(): void`
Drop the singleton (mainly for tests when config has changed).
```php
Mailer::reset();
```

### `->dispatch(Closure $callback): void`
Instance form of `send()`. The static `Mailer::send()` ultimately calls this.
```php
Mailer::instance()->dispatch(function ($message) {
    $message->to($to)->subject($subject)->text($body);
});
```

Both `send()` and `dispatch()` validate that the message has a `From` address (either configured default or set via `->from()`) and at least one recipient; otherwise they throw `Zero\Lib\Mail\MailException`.

---

## Message API

The `Message` instance is what you build inside the `send()` callback. Every setter returns `$this` for chaining.

### Recipients

#### `->from(string $address, ?string $name = null): self`
Override the configured default `from`.
```php
$message->from('no-reply@example.test', 'Zero Framework');
```

#### `->replyTo(string $address, ?string $name = null): self`
```php
$message->replyTo('support@example.test', 'Support Team');
```

#### `->to(string $address, ?string $name = null): self`
Add a recipient. Call multiple times to send to multiple addresses.
```php
$message->to('alice@example.test')->to('bob@example.test', 'Bob');
```

#### `->cc(string $address, ?string $name = null): self`
```php
$message->cc('manager@example.test', 'Manager');
```

#### `->bcc(string $address, ?string $name = null): self`
```php
$message->bcc('audit@example.test');
```

### Subject & body

#### `->subject(string $subject): self`
```php
$message->subject('Your invoice is ready');
```

#### `->text(string $body): self`
Plain-text body.
```php
$message->text("Hi Tofik,\n\nThanks for signing up.\n");
```

#### `->html(string $body): self`
HTML body. Pair with `view()` for templates.
```php
$message->html(view('mail.welcome', ['user' => $user]));
```

#### `->body(string $body, string $contentType): self`
Low-level body setter (use this for non-text/html content types).
```php
$message->body($json, 'application/json');
```

### Headers

#### `->header(string $name, string $value): self`
Add a custom header.
```php
$message
    ->header('X-Mailer', 'Zero/1.0')
    ->header('X-Priority', '1');
```

### Attachments

#### `->attach(string $filename, string $content, string $contentType = 'application/octet-stream'): self`
Attach in-memory bytes. Build a multipart/mixed message automatically.
```php
$pdf = file_get_contents(storage_path('invoices/INV-42.pdf'));
$message
    ->subject('Your invoice')
    ->html(view('mail.invoice', ['user' => $user]))
    ->attach('INV-42.pdf', $pdf, 'application/pdf');
```

For a file on disk:
```php
$message->attach('report.csv', file_get_contents($path), 'text/csv');
```

### Inspectors (used by transports / tests)

| Method | Returns |
| --- | --- |
| `->getFrom()` | `?array{address, name}` |
| `->getReplyTo()` | `?array{address, name}` |
| `->getTo()` / `->getCc()` / `->getBcc()` | `array<int, array{address, name}>` |
| `->getSubject()` | `string` |
| `->getBody()` | `string` |
| `->getContentType()` | `string` |
| `->getCustomHeaders()` | `array<string, string>` |
| `->getAttachments()` | `array<int, array{filename, content, contentType}>` |
| `->getEnvelopeRecipients()` | unique To + Cc + Bcc list |
| `->toMimeString()` | full MIME representation (multipart when attachments present) |

```php
$envelope = $message->getEnvelopeRecipients();
$rfc822   = $message->toMimeString();
```

---

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

Use the `Mail` facade (registered in `core/kernel.php`) to compose messages. The callback receives a `Zero\Lib\Mail\Message` instance with fluent helpers for addressing, headers, and content. The default `from` address is pulled from configuration, but you can override it per message.

```php
use Mail;

Mail::send(function ($mail) {
    $mail->to('user@example.com', 'Test User')
         ->subject('Welcome to Zero Framework')
         ->html('<p>Thanks for signing up!</p>');
});
```

### Override the From Address

```php
Mail::send(function ($mail) {
    $mail->from('team@example.com', 'Support Team')
         ->to('user@example.com')
         ->subject('We updated your account')
         ->text('Your preferences were updated successfully.');
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

### Attachments

```php
Mail::send(function ($mail) use ($pdfName, $pdfContents) {
    $mail->to('customer@example.com')
         ->subject('Invoice PDF')
         ->html('<p>Your invoice is attached.</p>')
         ->attach($pdfName, $pdfContents, 'application/pdf');
});
```

### Custom Headers

```php
Mail::send(function ($mail) {
    $mail->to('partner@example.com')
         ->subject('Webhook verification')
         ->header('X-Request-Id', $requestId)
         ->header('X-Environment', app_env())
         ->text('Verification payload attached.');
});
```

### Multiple Recipients

Each call to `to`, `cc`, or `bcc` appends a recipient, so you can chain or loop.

```php
Mail::send(function ($mail) use ($recipients) {
    foreach ($recipients as $recipient) {
        $mail->to($recipient['email'], $recipient['name'] ?? null);
    }

    $mail->subject('Weekly status')
         ->text('Team, here is the weekly status update.');
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
- Multipart/alternative payloads are not yet supported.
- TLS verification defaults to secure settings—loosen them only for local development.

Contributions are welcome! See `todo.md` for potential enhancements.
