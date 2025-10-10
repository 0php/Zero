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

## Common Tasks

- `php zero migrate` — apply outstanding migrations for the active connection.
- `php zero migrate:fresh` — drop all tables in the current database and rerun the migrations (handy for local resets).
- `php zero db:seed` — execute the default `DatabaseSeeder` (or pass a fully qualified class name).
- `php zero migrate --seed` — run migrations and then seed in a single shot when bootstrapping a new environment.

For query examples and model usage, see [DBML](dbml.md) and [Migrations & Schema Builder](migrations.md). When deploying, cross-check extension requirements and connection variables in [Deployment](deployment.md).
