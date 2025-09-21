# Migrations & Schema Builder

Zero splits its data tooling into two layers: **DBML (Database Management Layer)** handles fluent query building, while the migration API acts as a lightweight **DBAL (Database Abstraction Layer)** for structural changes. Use DBML when you need to read/write data, and the migration DBAL when you need to create or modify tables.

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
