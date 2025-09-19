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

- `--host`, `--port`, and `--root` mirror PHP's built-in server options.
- `--watch` enables a basic file watcher that restarts the server on changes.
- `--franken` and `--swolee` expose experimental server backends.

### Generate a Controller

```bash
php zero make:controller PostsController
```

Creates `app/controllers/PostsController.php` with a simple `index` action. Append `--force` to overwrite an existing file. The generator will add the `Controller` suffix automatically if it is not present.

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

Pass a number to `migrate:rollback` to reverse multiple batches, e.g. `php zero migrate:rollback 2`.

Modify tables with `Schema::table`:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('nickname', 50, true);
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

### Customising Stubs

Stub templates live under `core/templates`. Adjust `controller.tmpl` or `model.tmpl` (or add new files) to change the generated skeletons.

## Extending the CLI

Commands are dispatched from the `zero` script. New subcommands can be added by extending the `switch` statement and creating helper functions to encapsulate behaviour.
