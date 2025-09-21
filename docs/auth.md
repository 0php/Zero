# Authentication

Zero Framework now ships with a Laravel-style authentication flow covering registration, email verification, login, logout, and password resets. The stack remains dependency-free and relies on the framework's JWT-based session cookie and SMTP mailer.

## Overview

| Feature | Route | Controller |
| --- | --- | --- |
| Registration | `GET /register`, `POST /register` | `App\Controllers\Auth\RegisterController` |
| Email verification notice | `GET /email/verify` | `App\Controllers\Auth\EmailVerificationController@notice` |
| Email verification callback | `GET /email/verify/{token}` | `App\Controllers\Auth\EmailVerificationController@verify` |
| Resend verification email | `POST /email/verification-notification` | `App\Controllers\Auth\EmailVerificationController@resend` |
| Login / logout | `GET /login`, `POST /login`, `POST /logout` | `App\Controllers\Auth\AuthController` |
| Password reset request | `GET /password/forgot`, `POST /password/forgot` | `App\Controllers\Auth\PasswordResetController` |
| Password reset form | `GET /password/reset/{token}`, `POST /password/reset` | `App\Controllers\Auth\PasswordResetController` |

All generated mail is dispatched through the `Mail` facade (`Zero\Lib\Mail\Mailer`) and rendered with Blade-style views in `resources/views/mail`.

## Registration & Verification

1. Visitors submit the registration form (`resources/views/auth/register.php`).
2. `App\Controllers\Auth\RegisterController::store()` validates the payload, hashes the password with `Zero\Lib\Crypto::hash()`, creates the user, and delegates token creation/email delivery to `App\Services\Auth\EmailVerificationService`.
3. Verification tokens are stored in `email_verification_tokens` with a one-hour expiry. Links look like `/email/verify/{token}?email=jane@example.com`.
4. `App\Controllers\Auth\EmailVerificationController::verify()` ensures the token matches the email, checks expiry, marks `email_verified_at`, deletes outstanding tokens, and redirects to the login page with a success banner.
5. Users can request a new link from the verification notice screen. The controller sends another email without leaking whether an account exists.

## Login Flow

`App\Controllers\Auth\AuthController` preserves the original login/logout behaviour but now refuses access until `email_verified_at` is populated. When an unverified user attempts to sign in, the controller re-issues a verification email and flashes a helpful message.

Successful logins issue a signed JWT (via `Zero\Lib\Auth\Auth::login()`), stored in an HTTP-only cookie. Tokens honour `AUTH_TOKEN_TTL` (configured in `config/auth.php`, default 604800 seconds or 7 days), so tweak that value if you want different session lengths. The `Auth` facade exposes helpers such as `Auth::user()` and `Auth::id()` for downstream controllers, views, or middleware.

## Password Resets

1. Users initiate the flow from `/password/forgot`.
2. `App\Controllers\Auth\PasswordResetController::email()` records a hashed token in `password_reset_tokens` and sends a link using `App\Services\Auth\PasswordResetService`.
3. Tokens expire after 60 minutes and are unique per email. The reset link includes both the token and email, e.g. `/password/reset/{token}?email=jane@example.com`.
4. `App\Controllers\Auth\PasswordResetController::show()` validates the token before rendering the reset form (`resources/views/auth/reset-password.php`).
5. On submission `App\Controllers\Auth\PasswordResetController::update()` re-validates the token, hashes the new password, clears outstanding reset tokens, and redirects users back to the login page with a confirmation banner.

## Session Driver

Authentication state is now stored in the database by default. The framework registers a PDO-backed session handler that persists session payloads to the `sessions` table. You can opt into a cookie-backed driver instead (`SESSION_DRIVER=cookie`), which encrypts the session payload and stores it alongside the framework cookie settings. Configure the behaviour via `config/session.php` (and environment variables such as `SESSION_DRIVER`, `SESSION_LIFETIME`, and `SESSION_COOKIE`). Keep in mind that cookies have a ~4 KB size limit, so database sessions remain the safer choice for larger payloads.


## Database Schema

The authentication scaffolding relies on the following tables:

```text
users
  id bigint unsigned primary key
  name varchar(255)
  email varchar(255) unique
  password varchar(255)
  email_verified_at timestamp nullable
  remember_token varchar(100) null
  created_at / updated_at timestamps

email_verification_tokens
  id bigint unsigned primary key
  user_id bigint unsigned
  token char(64) unique
  expires_at timestamp (1 hour TTL)
  created_at timestamp default current_timestamp

password_reset_tokens
  id bigint unsigned primary key
  email varchar(255)
  token char(64) unique
  expires_at timestamp (1 hour TTL)
  created_at timestamp default current_timestamp
```

Run `php zero migrate` after configuring your `.env` to create the schema.

## Views & UX

All auth views use Bootstrap 5 and live under `resources/views/auth`:

- `login.php`
- `register.php`
- `verify-email.php`
- `forgot-password.php`
- `reset-password.php`

Email templates are rendered from `resources/views/mail/verify-email.php` and `resources/views/mail/reset-password.php`.

## Configuration Checklist

Update your `.env` (or environment) with:

```ini
APP_KEY=base64:...
APP_URL=http://localhost:8000
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="Zero Framework"
```

`APP_KEY` powers password hashing and encryption, while `APP_URL` is used to generate fully-qualified verification/reset links.

## Middleware & Guards

Use `App\Middlewares\Auth` to guard protected routes. The middleware stores the intended URL and redirects guests to `/login`. Once users are verified and authenticated, you can inspect their payload with the `Auth` facade.

Pair it with `App\Middlewares\Guest` for routes like login, registration, and password resets so authenticated users skip directly to their intended page instead of revisiting the auth forms.

Future enhancements (tracked in `todo.md`) include CSRF protection, richer role/permission guards, and queued mail delivery.

- The `UsersTableSeeder` seeds sample accounts for quick testing; run `php zero db:seed` to populate the table.
