# CLI Tooling

The `zero` executable provides a light command-line interface for common development tasks.

## Installation

The script ships with the repository; ensure it is executable:

```bash
chmod +x zero
```

## Commands

### Serve the Application

```bash
php zero serve [--host=127.0.0.1] [--port=8000] [--root=public] [--watch] [--franken] [--swolee]
```

- `--host`, `--port`, and `--root` mirror PHP's built-in server options (defaults fall back to the `HOST`, `PORT`, and `DOCROOT` environment variables when present).
- `--watch` enables a basic file watcher that restarts the server on changes.
- `--franken` and `--swolee` expose experimental server backends.

### Generate a Controller

```bash
php zero make:controller PostsController
```

Creates `app/controllers/PostsController.php` with a simple `index` action. Append `--force` to overwrite an existing file. The generator will add the `Controller` suffix automatically if it is not present.

### Generate a Service

```bash
php zero make:service Billing/Invoice
```

Creates `app/services/Billing/Invoice.php`, keeping the provided name intact and creating nested directories on demand. Use `--force` to overwrite an existing class. Both forward slashes and backslashes are accepted in the name.

### Generate a Helper

```bash
php zero make:helper randomText
```

Creates `app/helpers/RandomText.php` with a helper skeleton that exposes a default signature derived from the class name. Register the helper in `app/helpers/Helper.php` so it becomes available globally (for example, `random_text(10)`). Use `--force` to overwrite an existing helper.

### Generate a Model

```bash
php zero make:model Post
```

Creates `app/models/Post.php` extending the base `Zero\Lib\Model`. Use `--force` to regenerate an existing model class.

### Generate a Migration

```bash
php zero make:migration create_users_table
```

Creates a timestamped file in `database/migrations`. Each migration returns an anonymous class that extends `Zero\Lib\DB\Migration` with `up()` and `down()` methods.

Run outstanding migrations with:

```bash
php zero migrate
```

Rollback the latest batch:

```bash
php zero migrate:rollback [steps]
```

Reset the database by rolling back every batch and rerunning all migrations:

```bash
php zero migrate:refresh
```

Drop every table (ignoring individual `down()` methods) before running migrations from scratch:

```bash
php zero migrate:fresh
```

Migrations leverage the lightweight schema builder:

```php
use Zero\Lib\DB\Schema;
use Zero\Lib\DB\Blueprint;

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});
```

You can chain fluent modifiers on column definitions (e.g., `$table->string('email')->nullable()->unique();`).

Pass a number to `migrate:rollback` to reverse multiple batches, e.g. `php zero migrate:rollback 2`.

Modify tables with `Schema::table`:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('nickname', 50)->nullable();
    $table->dropColumn('legacy_field');
});
```

### Generate a Seeder

```bash
php zero make:seeder UsersTableSeeder
```

Seeders extend `Zero\Lib\DB\Seeder` and live in `database/seeders`. Execute a seeder with:

```bash
php zero db:seed Database\\Seeders\\UsersTableSeeder
```

### Generate a Middleware

```bash
php zero make:middleware EnsureAdmin
```

Creates `app/middlewares/EnsureAdminMiddleware.php` with a `handle()` stub ready for authentication or authorization logic. Use `--force` to overwrite an existing file.

### Link Storage

```bash
php zero storage:link
```

Creates symbolic links defined in `config/storage.php` (by default linking `public/storage` to the public disk). The command skips existing links and reports missing targets.

### Update to Latest Release

- Fetches a JSON manifest from the URL configured via `UPDATE_MANIFEST_URL`.
- Displays the target version and files that will be updated before applying changes.
- Downloads each file securely (with optional SHA-256 verification when provided in the manifest).
- Prompts for confirmation unless `--yes` is supplied.

After updating, review release notes, clear caches, and run migrations as needed.

### Customising Stubs

Stub templates live under `core/templates`. Adjust `controller.tmpl`, `service.tmpl`, or `model.tmpl` (or add new files) to change the generated skeletons.

## Extending the CLI

Commands are dispatched from the `zero` script. New subcommands can be added by extending the `switch` statement and creating helper functions to encapsulate behaviour.
