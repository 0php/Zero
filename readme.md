# Zero Framework

Zero Framework is a native-PHP micro-framework that mirrors the developer ergonomics of Laravel while avoiding external runtime dependencies. It keeps the stack lean, embraces expressive APIs for routing, HTTP handling, templating, and data access, and ships with a tiny CLI to cover the common workflows you expect from a modern framework.

## Table of Contents

- [Highlights](#highlights)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Project Layout](#project-layout)
- [Core Concepts](#core-concepts)
  - [Routing](#routing)
  - [Request & Response Lifecycle](#request--response-lifecycle)
  - [Views & Layouts](#views--layouts)
  - [Database & DBML](#database--dbml)
  - [Models](#models)
  - [Authentication](#authentication)
  - [Helpers & Facades](#helpers--facades)
  - [Support Utilities](#support-utilities)
- [CLI Reference](#cli-reference)
- [Configuration](#configuration)
- [Deployment](#deployment)
- [Documentation](#documentation)
- [Roadmap](#roadmap)
- [Inspiration](#inspiration)
- [Contributing](#contributing)
- [License](#license)

## Highlights

- Laravel-inspired developer experience with native PHP under the hood.
- Productive router with groups, middleware pipelines, and auto dependency injection.
- First-class HTTP abstractions: rich request helpers and flexible response factories.
- Blade-style view engine with layouts, sections, directives, and optional caching.
- Fluent DBML (Database Management Layer) query builder, active-record models, migrations, and seeders driven by a concise DBAL.
- Battery-included CLI (`zero`) for serving, scaffolding, and database management.
- SMTP mailer with fluent message composition and secure TLS defaults.
- Centralised error handler with configurable HTML/JSON output and configurable log channels (file or database).
- Simple `.env` loader with support for multiple environments and interpolation.

## Requirements

- PHP 8.1 or newer with the `pdo` extension (SQLite, MySQL, or PostgreSQL driver).
- Optional: `pcntl` extension for the `--watch` development server flag.
- Write access to the `storage/` directory for cache, logs, and compiled views.
- Composer is **not** required—the framework ships with its own autoloader.

## Installation

Get started with Zero Framework using this one-liner (replace `my-project` with your desired project name):

```bash
curl -L -o main.zip https://github.com/0php/Zero/archive/refs/heads/main.zip \
&& unzip -q main.zip \
&& rm main.zip \
&& mv Zero-main my-project \
&& cd my-project \
&& rm -rf docs todo.md readme.md .git \
&& php zero key:generate
```

## Quick Start

1. The installation script will create a new project and generate an application key.
2. Copy `.env.example` to `.env` and adjust host/port or database credentials as needed.
3. Ensure the `storage/` directory is writable (`chmod -R 775 storage`).
4. Serve the application:
   ```bash
   php zero serve --host=127.0.0.1 --port=8000 --root=public
   ```
   Add `--watch` to restart automatically on file changes, or try `--franken` / `--swolee` for experimental backends.
5. Visit the configured host/port to see the starter page.

Run migrations or seeders whenever you update the database schema:

```bash
php zero migrate
php zero db:seed Database\\Seeders\\DatabaseSeeder
```

## Project Layout

```text
app/                # Controllers, middlewares, and models for your application
config/             # Framework configuration (database, storage, view, etc.)
core/               # Framework libraries, bootstrap files, and infrastructure
database/           # Migrations and seeders
docs/               # In-depth documentation for each subsystem
public/             # HTTP entry point (`index.php`) and public assets
resources/          # View templates, layouts, and components
routes/             # Route definitions (`web.php`)
storage/            # Cache, compiled views, and runtime state
zero                # CLI entry point for serving and scaffolding
```

## Core Concepts

### Routing

- Define HTTP verbs and controller actions in `routes/web.php` using `Zero\Lib\Router` helpers (`get`, `post`, `put`, etc.).
- Group routes with shared prefixes or middleware; nested groups compose cleanly.
- Path parameters are type-hinted and injected automatically, along with the shared `Request` instance.
- Middleware can short-circuit by returning a response, ideal for guard logic.

### Request & Response Lifecycle

- `Zero\Lib\Http\Request::capture()` snapshots query, form, JSON, file, and header data, exposing helpers like `input`, `json`, `header`, `ip`, and `expectsJson`.
- Controllers may return strings, arrays, models, iterables, or explicit `Response` objects; the router normalises everything through `Zero\Lib\Response::resolve()`.
- Response factories cover HTML, JSON, text, redirects, streams, and opinionated API envelopes.

### Views & Layouts

- `Zero\Lib\View::render('pages/home', $data)` compiles Blade-inspired templates with layout, section, include, and directive support.
- Toggle view caching via `config/view.php` or `View::configure()`. Compiled templates live under `storage/cache/views`.
- Global `view()` and `response()` helpers simplify controller return values.

### Database & DBML

- `Zero\Lib\DB\DBML` (the Database Management Layer) provides fluent query building with selections, joins, aggregates, pagination, and safe bindings.
- Run migrations with `php zero migrate`; create new migrations via `php zero make:migration create_posts_table` and describe schema using the migration DBAL (`Zero\Lib\DB\Schema`).
- Seeders extend `Zero\Lib\DB\Seeder` and execute with `php zero db:seed`.

### Models

- Extend `Zero\Lib\Model` for active-record style classes with fillable attributes, timestamps, and lazy relationships (`hasOne`, `hasMany`, `belongsTo`).
- Chainable query builder methods return hydrated model instances; use `paginate()` or `simplePaginate()` for pagination.
- Call `toBase()` when you need the underlying DBML builder for complex queries.

### Authentication

- `App\Controllers\Auth\AuthController` handles login/logout and refuses access until the account is email verified.
- `App\Controllers\Auth\RegisterController`, `App\Controllers\Auth\EmailVerificationController`, and `App\Controllers\Auth\PasswordResetController` provide Laravel-style registration, verification, and password reset flows out of the box.
- Outbound mail (verification/reset links) is rendered with Blade-style views and delivered through the built-in SMTP mailer.
- Protect routes with `App\Middlewares\Auth`; unauthenticated requests are redirected to `/login` with the intended URL stored in the session.
- Access the current user via the `Auth` facade (`Auth::user()`, `Auth::id()`), or extend the controllers to suit domain-specific policies.

### Helpers & Facades

- `core/kernel.php` registers lightweight facades (`View`, `DB`, `Model`, `DBML`, `Auth`, `Mail`) and autoloaded helper files.
- Use the global `config()` helper for dot-access to `config/*.php`, and `env()` for values from `.env`, `.env.staging`, or `.env.production` (later files override earlier ones and support `${VAR}` interpolation).

### Support Utilities

- [`Zero\Lib\Http\Http`](docs/support.md#http-client) provides a fluent HTTP client for outbound requests (JSON helpers, timeouts, retries, file uploads).
- [`Zero\Lib\Support\Str`](docs/support.md#string-helpers) bundles familiar string transformations (studly, camel, snake, slug, etc.) for CLI and application code.

## CLI Reference

| Command                                                                      | Description                                                                       |
| ---------------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| `php zero serve [--host] [--port] [--root] [--watch] [--franken] [--swolee]` | Run the development server (with optional file watching or alternative backends). |
| `php zero make:controller Name [--force]`                                    | Generate a controller scaffold under `app/controllers`.                           |
| `php zero make:service Name [--force]`                                       | Generate a service class in `app/services`.                                       |
| `php zero make:model Name [--force]`                                         | Generate an active-record model in `app/models`.                                  |
| `php zero make:migration Name [--force]`                                     | Create a timestamped migration in `database/migrations`.                          |
| `php zero migrate`                                                           | Apply outstanding migrations.                                                     |
| `php zero migrate:rollback [steps]`                                          | Roll back the latest migration batches.                                           |
| `php zero migrate:refresh`                                                   | Roll back every migration batch, then rerun them.                                 |
| `php zero migrate:fresh`                                                     | Drop all tables and execute migrations from scratch.                              |
| `php zero make:seeder Name [--force]`                                        | Generate a seeder class in `database/seeders`.                                    |
| `php zero db:seed [FQN]`                                                     | Run a seeder (defaults to `Database\\Seeders\\DatabaseSeeder`).                   |

## Configuration

- Environment variables are loaded in priority order: `.env` → `.env.staging` → `.env.production`, with `${VAR}` interpolation and array syntax `[a,b,c]` support.
- Database connections live in `config/database.php` with drivers for MySQL, PostgreSQL, and SQLite. Switch drivers via `DB_CONNECTION` or driver-specific env vars (`MYSQL_HOST`, `SQLITE_DATABASE`, etc.).
- Sessions default to the database driver via `config/session.php`; adjust lifetime, cookie name, or fall back to file storage with `SESSION_DRIVER=file`.
- Logging is defined in `config/logging.php`. Switch between file and database channels with `LOG_DRIVER`, and point the database channel at a custom table via `LOG_TABLE`.
- Updater settings live in `config/update.php`. Set `UPDATE_MANIFEST_URL` (and optional `UPDATE_TIMEOUT`) to enable the `update:latest` command.
  - Leave `UPDATE_MANIFEST_URL` blank to pull the latest GitHub release (or branch) using `UPDATE_GITHUB_REPO` and `UPDATE_GITHUB_BRANCH`.
- Update `config/view.php`, `config/storage.php`, and other config files to match your deployment needs.

## Deployment

For a production-ready setup (Nginx + PHP-FPM, environment variables, logging, migrations), see the dedicated [deployment guide](docs/deployment.md). It covers server requirements, TLS, cron tasks, and the configurable logging channels.

## Documentation

- [Framework Overview](docs/overview.md)
- [Request & Response Lifecycle](docs/request-response.md)
- [Routing](docs/router.md)
- [DBML Query Builder](docs/dbml.md)
- [Model Layer](docs/models.md)
- [View Layer](docs/view.md)
- [Authentication](docs/auth.md)
- [Mailer](docs/mail.md)
- [CLI Tooling](docs/cli.md)

## Roadmap

Active ideas and upcoming improvements live in [`todo.md`](todo.md). Highlights include CSRF protection, richer CLI command dispatching, eager-loading helpers, and a centralised exception handler.

## Inspiration

This project draws inspiration from Laravel's elegance while celebrating the simplicity of native PHP—clean, self-sufficient code with a modern developer experience.

## Contributing

1. Fork the repository and create a feature branch.
2. Make your changes with clear commits and matching documentation updates.
3. Open a pull request describing the motivation and testing notes.

Bug reports and feature requests are welcome—open an issue or start a discussion.

## License

A license file has not been published yet. Add your preferred license at the repository root to clarify usage terms.
