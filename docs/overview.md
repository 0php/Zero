# Zero Framework Overview

Zero Framework is a lightweight, native-PHP micro-framework inspired by Laravel's developer experience. It keeps dependencies to a minimum while providing ergonomic tooling for routing, HTTP handling, templating, and database access.

## Architecture at a Glance

- **Entry point**: `public/index.php` bootstraps configuration, sessions, helpers, and delegates to the router.
- **Routing**: `Zero\Lib\Router` maps request URIs to controller actions, manages middleware, and resolves controller dependencies.
- **Request lifecycle**: `Zero\Lib\Http\Request` captures query, body, JSON, headers, and files into a reusable object for the duration of the request.
- **Request attributes**: Middleware can stash data via `Request::set()` and controllers can read it later with `Request::get()` or property access on the current instance.
- **Response pipeline**: Controllers can return any scalar, array, object, or `Zero\Lib\Http\Response`. The router normalises these via `Response::resolve()` before sending the payload to the client.
- **Views**: `Zero\Lib\View` renders PHP templates with Blade-inspired directives, layout/section support, and optional caching.
- **Database access (DBML)**: `Zero\Lib\DB\DBML`—the Database Management Layer—provides a fluent query builder atop the framework's PDO bridge.
- **Models**: `Zero\Lib\Model` offers an active-record style abstraction that hydrates results into rich PHP objects.
- **Helpers**: `registerHelper()` wires app-specific helper classes into globally callable functions (generate stubs with `php zero make:helper`).
- **Migrations & Seeders (DBAL)**: CLI commands (`migrate`, `make:migration`, `db:seed`) drive the migration DBAL for schema changes and database seeding.
- **Mailing**: `Zero\Lib\Mail\Mailer` wraps SMTP delivery with fluent message composition and dotenv-driven configuration.
- **HTTP Client**: [`Zero\Lib\Http\Http`](support.md#http-client) exposes a fluent, cURL-backed client for outbound requests with JSON helpers and timeout configuration.
- **String Utilities**: [`Zero\Lib\Support\Str`](support.md#string-helpers) bundles common string transformations (studly, snake, camel, slug, etc.) for CLI and app code.
- **Storage**: [`Zero\Lib\Storage\Storage`](support.md#storage) writes files to the configured disks; uploaded files call `$file->store()` to persist content.

## Next Steps

- Explore the [request/response lifecycle](request-response.md) for details on how input is captured and responses are emitted.
- Review [routing](router.md) to understand grouping, middleware, and parameter binding.
- Dive into [DBML](dbml.md) for building SQL queries fluently.
- Explore the [model layer](models.md) to work with active-record style objects.
- Review the [migrations guide](migrations.md) to learn how to shape tables, tweak charset/collation, and modify columns safely.
- Review the [CLI tooling](cli.md) for scaffolding, migrations, and seeding workflows.
- Learn how to compose templates in the [view layer](view.md).
- Review the [authentication guide](auth.md) for protecting routes and handling sessions.
- Learn how to send email with the [SMTP mailer](mail.md).
- Browse the [CLI reference](cli.md) to discover available tooling.
- Read through the [support utilities](support.md) for the HTTP client and string helper reference.


## Deployment

For production configuration, consult [docs/deployment.md](deployment.md) for web server examples, environment setup, logging, and post-deploy checklists.
