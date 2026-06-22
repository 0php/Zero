# Database Connections

Zero ships with lightweight PDO bridges for MySQL/MariaDB, PostgreSQL, and SQLite. Each connection shares the same DBML query builder and migration tooling, so switching drivers only requires environment changes—your application code stays untouched.

## Choosing a Driver

Set `DB_CONNECTION` in your `.env` file (or server environment) to pick the backing database:

```ini
# mysql, postgres, or sqlite
DB_CONNECTION=mysql
```

Every driver pulls its credentials from `config/database.php`. Override the defaults with matching environment variables rather than editing the config file directly—this keeps staging/production secrets out of source control.

Run `php zero migrate` after updating credentials to confirm the driver can connect and apply migrations.

## MySQL / MariaDB

Zero uses PHP's `pdo_mysql` extension and works with MySQL 5.7+, MySQL 8.x, and MariaDB. Ensure the extension is enabled in your PHP build.

```ini
DB_CONNECTION=mysql
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_DATABASE=zero
MYSQL_USER=root
MYSQL_PASSWORD=secret
MYSQL_CHARSET=utf8mb4
MYSQL_COLLATION=utf8mb4_general_ci
```

- `MYSQL_CHARSET` and `MYSQL_COLLATION` feed both the connection and the migration defaults; adjust them if you need a non-UTF8 database.
- Create the database (`CREATE DATABASE zero;`) before running migrations, or let your provisioning scripts handle it.
- Use `php zero migrate` to apply schema changes and `php zero db:seed` to load seed data once the connection is configured.

## PostgreSQL

The PostgreSQL driver leverages `pdo_pgsql`. Install it alongside a server version 12+ (earlier releases typically work, but newer versions receive more coverage in tests).

```ini
DB_CONNECTION=postgres
POSTGRES_HOST=127.0.0.1
POSTGRES_PORT=5432
POSTGRES_DATABASE=zero
POSTGRES_USER=zero
POSTGRES_PASSWORD=secret
POSTGRES_CHARSET=UTF8
```

- `POSTGRES_CHARSET` controls the client encoding; keep it in sync with the database locale (the installer uses `UTF8` by default).
- Provision the database with `createdb zero` or `CREATE DATABASE zero OWNER zero;` before running migrations.
- If you need SSL or Unix socket connections, extend the DSN in `config/database.php` or add connection options via environment overrides.

## SQLite

SQLite is ideal for single-user projects, tests, or CLI tooling. Zero talks to it through `pdo_sqlite`.

```ini
DB_CONNECTION=sqlite
SQLITE_DATABASE=/absolute/path/to/storage/sqlite/zero.sqlite
```

- The default `.env.example` uses `base('sqlite/zero.sqlite')`; ensure the `sqlite/` directory is writable by the PHP process.
- SQLite creates the database file automatically when you run `php zero migrate`, so no manual provisioning is necessary.
- Because SQLite locks the database file per write, avoid using it for high-concurrency web workloads.

## Multiple Connections

`config/database.php` can hold more than one connection definition. The top-level `connection` key names the default; every other key (`mysql`, `postgres`, `sqlite`, or any custom name you add) is a connection your code can target by name.

```php
// config/database.php
return [
    'connection' => env('DB_CONNECTION', 'mysql'),

    'mysql' => [ /* primary app database */ ],

    'analytics' => [
        'driver'   => 'mysql',
        'host'     => env('ANALYTICS_HOST', '127.0.0.1'),
        'database' => env('ANALYTICS_DATABASE', 'analytics'),
        'username' => env('ANALYTICS_USER', 'root'),
        'password' => env('ANALYTICS_PASSWORD', ''),
        'charset'  => 'utf8mb4',
        'collation'=> 'utf8mb4_general_ci',
    ],
];
```

### Running queries on a specific connection

The `Database` facade ([`Database.php`](../core/libraries/Database/Database.php)) lets you pick a connection per query or for a scoped block:

```php
use Zero\Lib\Database;

// One-off connection instance (does not change the global default).
$rows = Database::on('analytics')->fetch('SELECT * FROM events LIMIT 10');

// Run a callback with a connection active; the previous connection is
// restored afterward, even if the callback throws.
$total = Database::withConnection('analytics', function () {
    return Database::fetch('SELECT COUNT(*) AS c FROM events')[0]['c'];
});
```

The active connection is tracked on a stack, so `withConnection()` calls nest correctly. The lower-level primitives are available if you need manual control:

| Method | Purpose |
| --- | --- |
| `Database::on(string $name): DatabaseConnection` | Get a connection instance for `$name` without touching the active stack. |
| `Database::withConnection(?string $name, callable $cb): mixed` | Run `$cb` with `$name` active, then restore. Preferred for scoped work. |
| `Database::useConnection(?string $name): void` | Push `$name` onto the active stack manually. |
| `Database::popConnection(): void` | Pop the most recently pushed connection. |
| `Database::activeConnection(): ?string` | Name of the connection currently on top of the stack (`null` = default). |

Passing `null` (or omitting the name) always resolves to the default connection from `config('database.connection')`, so existing single-connection code keeps working unchanged.

### Per-model connections

A model can pin itself to a non-default connection with the `$connection` property ([`Model.php`](../core/libraries/Model/Model.php)). Every read and write for that model — including its query builder and relation operations — then runs on that connection automatically:

```php
namespace App\Models;

use Zero\Lib\Model;

class Event extends Model
{
    protected ?string $connection = 'analytics';
    protected static string $table = 'events';
}
```

```php
Event::all();                 // runs on the 'analytics' connection
Event::where('type', 'click')->count();
$event->save();               // writes go to 'analytics' too
```

Leave `$connection` as `null` (the default) to use the application's default connection.

## Common Tasks

- `php zero migrate` — apply outstanding migrations for the active connection.
- `php zero migrate:fresh` — drop all tables in the current database and rerun the migrations (handy for local resets).
- `php zero db:seed` — execute the default `DatabaseSeeder` (or pass a fully qualified class name).
- `php zero migrate --seed` — run migrations and then seed in a single shot when bootstrapping a new environment.

For query examples and model usage, see [DBML](dbml.md) and [Migrations & Schema Builder](migrations.md). When deploying, cross-check extension requirements and connection variables in [Deployment](deployment.md).
