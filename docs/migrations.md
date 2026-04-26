# Migrations & Schema Builder

Zero splits data tooling into two layers: **DBML** for fluent query building (see [dbml.md](dbml.md)), and the migration API — a lightweight DBAL for structural changes.

```php
use Zero\Lib\DB\Schema;
use Zero\Lib\DB\Blueprint;
```

Implementation: [`Schema.php`](../core/libraries/DB/Schema.php), [`Blueprint.php`](../core/libraries/DB/Blueprint.php), [`Migrator.php`](../core/libraries/DB/Migrator.php), [`Migration.php`](../core/libraries/DB/Migration.php).

---

## Schema API

### `Schema::create(string $table, Closure $callback): void`
Create a new table.
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email')->unique();
    $table->string('password');
    $table->timestamp('email_verified_at')->nullable();
    $table->timestamps();
});
```

### `Schema::table(string $table, Closure $callback): void`
Modify an existing table.
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable();
    $table->index('email');
});
```

### `Schema::drop(string $table): void`
```php
Schema::drop('legacy_logs');
```

### `Schema::dropIfExists(string $table): void`
```php
Schema::dropIfExists('legacy_logs');
```

### `Schema::dropColumn(string $table, string $column): void`
```php
Schema::dropColumn('users', 'phone');
```

### `Schema::dropColumnIfExists(string $table, string $column): void`
```php
Schema::dropColumnIfExists('users', 'phone');
```

### `Schema::startTransaction(): void` / `Schema::commit(): void` / `Schema::rollback(): void`
Wrap multiple structural changes in a transaction.
```php
Schema::startTransaction();
try {
    Schema::create('a', /* ... */);
    Schema::create('b', /* ... */);
    Schema::commit();
} catch (\Throwable $e) {
    Schema::rollback();
    throw $e;
}
```

---

## Blueprint API

The `$table` passed to `Schema::create` / `Schema::table` is a `Blueprint`. Every column method returns a `ColumnDefinition` you can chain modifiers on.

### Primary keys

#### `->id(string $column = 'id'): ColumnDefinition`
Auto-incrementing big-integer primary key.
```php
$table->id();
```

#### `->increments(string $column): ColumnDefinition`
Auto-incrementing primary key with explicit name.
```php
$table->increments('order_id');
```

#### `->uuidPrimary(string $column = 'id'): ColumnDefinition`
UUID primary key.
```php
$table->uuidPrimary();
```

### Numeric columns

#### `->integer(string $column, bool $unsigned = false, bool $nullable = false, mixed $default = null): ColumnDefinition`
```php
$table->integer('age', unsigned: true, default: 0);
```

#### `->bigInteger(string $column, bool $unsigned = false, bool $nullable = false, mixed $default = null): ColumnDefinition`
```php
$table->bigInteger('amount', unsigned: true);
```

#### `->decimal(string $column, int $precision = 10, int $scale = 2, bool $nullable = false, mixed $default = null): ColumnDefinition`
```php
$table->decimal('total', precision: 12, scale: 2);
```

### String / text columns

#### `->string(string $column, int $length = 255, bool $nullable = false, mixed $default = null): ColumnDefinition`
```php
$table->string('email');
$table->string('slug', length: 64)->unique();
```

#### `->text(string $column, bool $nullable = true): ColumnDefinition`
```php
$table->text('body');
```

#### `->longText(string $column, bool $nullable = true): ColumnDefinition`
```php
$table->longText('payload');
```

#### `->enum(string $column, array $allowed, bool $nullable = false, mixed $default = null): ColumnDefinition`
```php
$table->enum('status', ['draft', 'published', 'archived'], default: 'draft');
```

### Booleans / UUID

#### `->boolean(string $column, bool $nullable = false, bool $default = false): ColumnDefinition`
```php
$table->boolean('active', default: true);
```

#### `->uuid(string $column = 'uuid', bool $primary = false): ColumnDefinition`
```php
$table->uuid('public_id')->unique();
```

### Date & time

#### `->date(string $column, bool $nullable = false, mixed $default = null): ColumnDefinition`
```php
$table->date('birthday', nullable: true);
```

#### `->datetime(string $column, bool $nullable = false, mixed $default = null): ColumnDefinition`
```php
$table->datetime('scheduled_at')->nullable();
```

#### `->timestamp(string $column, bool $nullable = false, mixed $default = null): ColumnDefinition`
```php
$table->timestamp('email_verified_at')->nullable();
$table->timestamp('created_at')->useCurrent();
```

#### `->timestamps(): self`
Adds `created_at` and `updated_at`.
```php
$table->timestamps();
```

#### `->softDeletes(): self`
Adds nullable `deleted_at`.
```php
$table->softDeletes();
```

### Foreign keys

#### `->foreignId(string $column, bool $nullable = false): ColumnDefinition`
Pair with `->constrained()` (or `references()`/`on()`) to add a foreign-key constraint.
```php
$table->foreignId('user_id')->constrained();             // → users(id)
$table->foreignId('author_id')->constrained('users');
$table->foreignId('post_id')->references('id')->on('posts')->onDelete('cascade');
```

### Indexes

#### `->index(string|array $columns, ?string $name = null): self`
```php
$table->index('email');
$table->index(['account_id', 'status'], 'orders_acct_status_idx');
```

#### `->unique(string|array $columns, ?string $name = null): self`
```php
$table->unique('email');
$table->unique(['org_id', 'slug']);
```

#### `->primary(string|array $columns, ?string $name = null): self`
Composite primary key.
```php
$table->primary(['user_id', 'role_id']);
```

### Modify / drop columns

#### `->dropColumn(string $column): self`
```php
Schema::table('users', function ($table) {
    $table->dropColumn('phone');
});
```

#### `->renameColumn(string $from, string $to): self`
```php
Schema::table('users', function ($table) {
    $table->renameColumn('username', 'handle');
});
```

#### `->raw(string $definition): self`
Drop a raw SQL fragment into the create/alter statement.
```php
$table->raw('FULLTEXT (title, body)');
```

### Charset / collation

#### `->charset(string $charset): self` / `->collation(string $collation): self`
```php
$table->charset('utf8mb4');
$table->collation('utf8mb4_unicode_ci');
```

---

## Column modifiers

`ColumnDefinition` (returned by every column method) supports a chain of modifiers:

| Modifier | Example |
| --- | --- |
| `->nullable(bool $value = true)` | `$table->string('phone')->nullable();` |
| `->default(mixed $value)` | `$table->integer('count')->default(0);` |
| `->useCurrent()` | `$table->timestamp('created_at')->useCurrent();` |
| `->unsigned(bool $value = true)` | `$table->integer('age')->unsigned();` |
| `->primary(?string $name = null)` | `$table->string('code')->primary();` |
| `->unique(?string $name = null)` | `$table->string('email')->unique();` |
| `->index(?string $name = null)` | `$table->string('slug')->index();` |
| `->references($column)` / `->on($table)` | `$table->foreignId('user_id')->references('id')->on('users');` |
| `->constrained(?string $table = null, string $column = 'id')` | `$table->foreignId('user_id')->constrained();` |
| `->onDelete(string $action)` / `->onUpdate(string $action)` | `->onDelete('cascade')` |
| `->foreignKeyName(string $name)` | `->foreignKeyName('orders_user_fk')` |
| `->charset(string $charset)` | `->charset('utf8mb4')` |
| `->collation(string $collation)` / `->collate(...)` | `->collation('utf8mb4_unicode_ci')` |
| `->change()` | mark column as a modify-existing op |

```php
Schema::table('users', function ($table) {
    $table->string('email', 320)->change();    // resize an existing column
});
```

---

## Writing a migration

Generate one with the CLI:
```bash
php zero make:migration create_users_table
```

The generated stub looks like:
```php
namespace Database\Migrations;

use Closure;
use Zero\Lib\DB\Blueprint;
use Zero\Lib\DB\Migration;
use Zero\Lib\DB\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

Run all pending migrations:
```bash
php zero migrate
```

Roll back, refresh, or wipe:
```bash
php zero migrate:rollback
php zero migrate:rollback 3       # last 3 batches
php zero migrate:refresh          # rollback all then re-run
php zero migrate:fresh            # drop all tables then re-run
```

## Transactions

Schema changes can be wrapped in transactions using the `Schema` facade (aliases the `Database` transaction helpers). Note that some databases auto-commit certain DDL statements.

```php
Schema::startTransaction();

try {
    Schema::table('users', function ($table) {
        $table->string('nickname')->nullable();
    });

    Schema::commit();
} catch (Throwable $e) {
    Schema::rollback();
    throw $e;
}
```

## Connection Charset & Collation

Driver defaults now read from your environment configuration:

```ini
MYSQL_CHARSET=utf8mb4
MYSQL_COLLATION=utf8mb4_general_ci
POSTGRES_CHARSET=UTF8
```

MySQL and PostgreSQL connections pick up these values automatically (falling back to UTF-8 sensible defaults). Override them per connection if you need a different encoding. See `.env.example` for the default values that ship with the framework.

## Table-Level Defaults

Control default encodings directly from your migration:

```php
use Zero\Lib\DB\Schema;

Schema::create('posts', function ($table) {
    $table->charset('utf8mb4');
    $table->collation('utf8mb4_general_ci');

    $table->id();
    $table->string('title');
    $table->text('body');
    $table->timestamps();
});
```

When altering an existing table, the same methods emit the relevant `ALTER TABLE ... DEFAULT CHARACTER SET / COLLATE` statements on MySQL:

```php
Schema::table('posts', function ($table) {
    $table->charset('utf8mb3');
    $table->collation('utf8mb3_general_ci');
});
```

## Column Charset & Collation

Column helpers allow per-column overrides:

```php
Schema::create('customers', function ($table) {
    $table->id();
    $table->string('name')
        ->charset('utf8mb4')
        ->collation('utf8mb4_general_ci');

    $table->string('legacy_code', 32)
        ->charset('latin1')
        ->collation('latin1_swedish_ci');
});
```

Use `charset($value)` to switch the character set and `collation($value)` (or the alias `collate($value)`) to tweak the collation.

## Column Reference

Every column helper returns a `ColumnDefinition`, so you can chain modifiers such as `nullable()`, `default()`, `charset()`, `collation()`, `index()`, or `change()`.

```php
Schema::create('example', function ($table) {
    $table->id();                        // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
    $table->increments('legacy_id');     // Alias of id()
    $table->integer('age');              // INT
    $table->bigInteger('views');         // BIGINT
    $table->string('name', 255);         // VARCHAR(255)
    $table->text('biography');           // TEXT
    $table->longText('content');         // LONGTEXT
    $table->enum('status', ['draft', 'published']);
    $table->boolean('is_active', default: true);
    $table->uuid('uuid');
    $table->uuidPrimary();
    $table->timestamp('published_at');
    $table->datetime('archived_at', nullable: true);
    $table->foreignId('user_id')->constrained();
    $table->softDeletes();
    $table->timestamps();
});
```

### Column Modifiers

- `nullable()` – mark the column nullable.
- `default($value)` – define a default (strings are automatically quoted).
- `unsigned()` – available on numeric types.
- `primary()`, `unique()`, `index()` – quick index/constraint helpers.
- `charset($charset)` – override character set for the column (MySQL only).
- `collation($collation)` / `collate($collation)` – override the column collation (MySQL only).
- `useCurrent()` – set TIMESTAMP columns to default to `CURRENT_TIMESTAMP`.
- `references($column)` + `on($table)` – pair to declare foreign key targets.
- `onDelete($action)` / `onUpdate($action)` – specify cascading behaviour for foreign keys.
- `foreignKeyName($name)` – override the generated foreign key constraint name.
- `change()` – alter an existing column when used inside `Schema::table()`.

### Foreign Keys

Chain the foreign key helpers together for clarity:

```php
Schema::table('orders', function ($table) {
    $table->foreignId('user_id')
        ->constrained('users')
        ->onDelete('cascade')
        ->onUpdate('cascade');
});
```

Prefer the explicit helpers when you need full control over names or cascading rules:

```php
Schema::table('invoices', function ($table) {
    $table->unsignedBigInteger('customer_id');

    $table->foreignId('customer_id')
        ->references('id')
        ->on('customers')
        ->onDelete('restrict')
        ->onUpdate('cascade')
        ->foreignKeyName('invoices_customer_fk');
});
```

### Table Helpers

- `charset($charset)` / `collation($collation)` – set table defaults (MySQL only).
- `timestamps()` – adds `created_at` and `updated_at`.
- `softDeletes()` – adds nullable `deleted_at`.
- `foreignId()` + `constrained()` – declare foreign key columns succinctly.
- `dropColumn($name)` – remove a column.
- `renameColumn($from, $to)` – rename a column.
- `raw($definition)` – inject custom SQL when you need something low-level.

### Schema Facade Shortcuts

- `Schema::create($table, $callback)` – create a table.
- `Schema::table($table, $callback)` – alter an existing table.
- `Schema::drop($table)` / `dropIfExists($table)` – remove tables.
- `Schema::dropColumn($table, $column)` / `dropColumnIfExists($table, $column)` – drop columns imperatively.

## Changing Existing Columns

Alter columns in place with `change()`:

```php
Schema::table('customers', function ($table) {
    $table->string('name', 255)
        ->charset('utf8mb4')
        ->collation('utf8mb4_general_ci')
        ->change();
});
```

On MySQL this compiles to `ALTER TABLE ... MODIFY COLUMN ...`. Combine it with other modifiers (`nullable()`, `default()`, etc.) as needed. Other drivers may ignore charset/collation directives or require additional statements.

## Recap

- Connection defaults originate from `.env` and are applied automatically.
- Use `$table->charset()` / `$table->collation()` for table-wide defaults.
- Override individual columns with `->charset()` / `->collation()`.
- Call `->change()` within `Schema::table()` when modifying existing columns.

With these additions you can fine-tune encodings, collations, and column definitions without dropping into raw SQL. DBML remains your go-to for querying data, while the migration DBAL keeps schema changes expressive and safe.
