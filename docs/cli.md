# CLI Tooling

The `zero` executable provides a command-line interface for common development tasks. Each command is implemented as a class under [`core/libraries/Console/Commands/`](../core/libraries/Console/Commands/) and dispatched by [`Application.php`](../core/libraries/Console/Application.php).

```bash
php zero <command> [options...]
php zero --help              # list all commands
php zero <command> --help    # show usage for one command
```

## Installation

The script ships with the repository; ensure it is executable:

```bash
chmod +x zero
```

## Command index

| Group | Commands |
| --- | --- |
| Server | [`serve`](#serve-the-application) |
| Generators | [`make:controller`](#generate-a-controller), [`make:service`](#generate-a-service), [`make:model`](#generate-a-model), [`make:middleware`](#generate-a-middleware), [`make:helper`](#generate-a-helper), [`make:logger`](#generate-a-logger), [`make:command`](#generate-a-console-command), [`make:migration`](#generate-a-migration), [`make:seeder`](#generate-a-seeder) |
| Database | [`migrate`](#run-migrations), [`migrate:rollback`](#rollback-migrations), [`migrate:refresh`](#refresh-migrations), [`migrate:fresh`](#fresh-migrations), [`db:seed`](#run-a-seeder), [`db:dump`](#dump-the-database), [`db:restore`](#restore-the-database) |
| App | [`key:generate`](#generate-app-key), [`route:list`](#inspect-registered-routes), [`storage:link`](#link-storage), [`log:clear`](#clear-log-files), [`schedule:run`](#run-scheduled-tasks) |

---

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

### Generate a Console Command

```bash
php zero make:command HealthCheck --signature=app:health
```

Creates `app/console/Commands/HealthCheck.php` implementing the `CommandInterface`. The generator also ensures `app/console/Commands/Command.php` exists and appends the new command to its registration list so it is immediately available to the CLI. Provide `--description="..."` to customise the help text and `--force` to overwrite an existing command class.

### Generate a Logger

```bash
php zero make:logger SlackLogger
```

Creates `app/loggers/SlackLogger.php` implementing `Zero\Lib\Log\LogHandlerInterface`. Use it to add custom destinations (Slack webhooks, third-party services, files) for the framework's logging facade. Pass `--force` to overwrite an existing logger class.

### Generate a Model

```bash
php zero make:model Post
```

Creates `app/models/Post.php` extending the base `Zero\Lib\Model`. Use `--force` to regenerate an existing model class.

### Generate a Migration

```bash
php zero make:migration create_users_table
```

Creates a timestamped file in `database/migrations`. Each migration returns an anonymous class that extends `Zero\Lib\DB\Migration` with `up()` and `down()` methods. See [migrations.md](migrations.md) for the full schema-builder reference.

### Run Migrations

```bash
php zero migrate
```

Apply every outstanding migration in order. Each batch is recorded so it can be rolled back independently.

### Rollback Migrations

```bash
php zero migrate:rollback        # last batch only
php zero migrate:rollback 3      # last 3 batches
```

### Refresh Migrations

```bash
php zero migrate:refresh
```

Roll back every migration batch and re-run them — useful when iterating on schema changes during development.

### Fresh Migrations

```bash
php zero migrate:fresh
```

Drop every table in the database (ignoring individual `down()` methods) and run all migrations from scratch. Faster than `migrate:refresh` when downs are slow or missing.

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

Seeders extend `Zero\Lib\DB\Seeder` and live in `database/seeders`.

### Run a Seeder

```bash
php zero db:seed                                          # runs DatabaseSeeder by default
php zero db:seed Database\\Seeders\\UsersTableSeeder      # explicit FQCN
```

The default `DatabaseSeeder` is expected at `database/seeders/DatabaseSeeder.php` — call sub-seeders from its `run()` method to chain multiple seed scripts.

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

### Clear Log Files

```bash
php zero log:clear [--channel=file] [--path=/absolute/log/path]
```

Removes `*.log` files from the target directory. When `--path` is omitted the command resolves the directory from `config/logging.php` (defaulting to `storage/framework/logs`). Only log files are deleted—placeholder files (such as `.gitignore`) remain.

### Dump the Database

```bash
php zero db:dump [--connection=mysql] [--file=storage/database/dumps/backup.sql]
```

Exports the configured database to an SQL dump. Provide `--file` to choose the destination path (relative paths are resolved against the current working directory); otherwise the dump is written to `storage/database/dumps/<connection>-<timestamp>.sql`. The command supports MySQL (`mysqldump`), PostgreSQL (`pg_dump`), and SQLite (just copies the database file).

### Restore the Database

```bash
php zero db:restore [--connection=mysql] [--file=storage/database/dumps/backup.sql]
```

Restores an SQL dump back into the configured database. When `--file` is omitted the most recent dump in `storage/database/dumps` is used; specify an explicit path to restore a custom dump. The command supports MySQL (`mysql`), PostgreSQL (`psql`), and SQLite.

### Inspect Registered Routes

```bash
php zero route:list
```

Bootstraps `routes/web.php` and prints a table with the HTTP method, URI, route name (when available), controller action, and attached middleware stack. Use it to confirm route bindings after adding groups, name prefixes, or new controllers.

### Generate App Key

```bash
php zero key:generate
```

Generates a base64-encoded random key and writes it to `.env` as `APP_KEY=base64:...`. The key is used for password hashing, JWT signing, and cookie encryption — keep it stable across deploys but never commit it to git. Re-running the command rotates the key (existing JWT cookies/sessions become invalid).

### Run Scheduled Tasks

```bash
php zero schedule:run
```

Bootstraps `routes/cron.php` and executes every task whose cron expression matches the current minute. Wire it into your system's crontab once a minute:

```cron
* * * * * cd /var/www/app && php zero schedule:run >> /dev/null 2>&1
```

See [cron.md](cron.md) for task definitions.

### Update to Latest Release

- Fetches a JSON manifest from the URL configured via `UPDATE_MANIFEST_URL`.
- Displays the target version and files that will be updated before applying changes.
- Downloads each file securely (with optional SHA-256 verification when provided in the manifest).
- Prompts for confirmation unless `--yes` is supplied.

After updating, review release notes, clear caches, and run migrations as needed.

### Customising Stubs

Stub templates live under `core/templates`. Adjust `controller.tmpl`, `service.tmpl`, or `model.tmpl` (or add new files) to change the generated skeletons.

## Extending the CLI

Custom commands live under `app/console/Commands/`. The fastest path is `php zero make:command`, which generates a stub and registers it automatically:

```bash
php zero make:command HealthCheck --signature=app:health --description="Probe service health"
```

The generated class implements `CommandInterface`:

```php
namespace App\Console\Commands;

use Zero\Lib\Console\Contracts\CommandInterface;

class HealthCheck implements CommandInterface
{
    public function getName(): string         { return 'app:health'; }
    public function getDescription(): string  { return 'Probe service health'; }
    public function getUsage(): string        { return 'php zero app:health'; }

    public function execute(array $argv): int
    {
        // ... your logic ...
        return 0;
    }
}
```

`app/console/Commands/Command.php` (generated on first use) lists every custom command. The framework merges it with the built-in command set when the CLI boots.
