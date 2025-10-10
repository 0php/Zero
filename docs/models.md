# Models

`Zero\Lib\Model` provides a lightweight active-record layer on top of DBML. Extend it inside `App\Models` to describe your tables, hydrate records into rich objects, and define relationships between entities.

```php
namespace App\Models;

use Zero\Lib\Model;

class User extends Model
{
    protected array $fillable = ['name', 'email'];
}
```

## Table & Key Configuration

Models infer table and key information from the class name, but you can override any piece:

- `protected ?string $table` – set manually when the pluralised class name does not match your table.
- `protected string $primaryKey = 'id'` – customise the primary key column.
- `protected bool $incrementing = true` – disable when using non-incrementing identifiers.
- `protected bool $usesUuid = true` / `protected ?string $uuidColumn` – generate a v4 UUID automatically on insert.

## Mass Assignment & Attributes

- `protected array $fillable = []` – whitelist of attributes accepted by `fill()`/`create()`. Leave empty to allow all attributes.
- `fill(array $attributes)` honours the whitelist, `forceFill()` bypasses it.
- `getAttribute()`, `hasAttribute()`, and magic accessors (`$user->name`) expose attributes and loaded relations.
- Convert models to arrays or JSON via `toArray()` / `jsonSerialize()`.

Dirty tracking happens automatically: `isDirty()` checks if any attribute changed compared to the original snapshot, and `getDirty()` (from the trait) lists the mutated fields.

## Timestamps & Soft Deletes

- `protected bool $timestamps = true` keeps `created_at` / `updated_at` (customise names with `$createdAtColumn` / `$updatedAtColumn`).
- Set `protected bool $softDeletes = true` and ensure a nullable `deleted_at` column exists. Helpers:
  - `trashed()` – has the model been soft deleted?
  - `restore()` / `forceDelete()` – undo or bypass soft deletes.
  - Query builder scopes: `withTrashed()`, `onlyTrashed()`, `withoutTrashed()`.

## Querying Models

`Model::query()` returns a `ModelQuery` wrapper around DBML. Core entry points:

```php
$active = User::query()
    ->where('status', 'active')
    ->orderByDesc('created_at')
    ->get();

$john = User::find(42);
$all  = User::all();
$recentPage = User::paginate(20, page: 1);
```

The builder supports the full DBML API (`where`, `whereAny`, `whereAnyLike`, `when`, `whereExists`, joins, aggregates, etc.). Refer to the [DBML guide](dbml.md) for the comprehensive query surface.

### Eager Loading & Relationship Filters

```php
$users = User::with(['posts', 'posts.comments'])
    ->withCount('posts')
    ->whereHas('posts', fn ($query) => $query->where('published', true))
    ->orderBy('name')
    ->get();

$lurkers = User::query()->whereDoesntHave('posts')->get();
```

`with()`, `withCount()`, `whereHas()` / `whereDoesntHave()` (and their `or...` variants) mirror Eloquent style APIs while delegating to DBML under the hood.

### Dropping Down to DBML

Need plain arrays or custom SQL? Use `toBase()` to obtain the underlying DBML builder, or call `toSql()` / `getBindings()` on `ModelQuery` to inspect generated SQL.

## Creating, Updating & Deleting

```php
$user = User::create([
    'name' => 'Ada Lovelace',
    'email' => 'dev@zerophp.com',
]);

$user->update(['name' => 'Augusta Ada']);
$user->delete();          // respects soft deletes if enabled
$user->forceDelete();     // always removes the record
$user->restore();         // bring back a soft-deleted record

// Upsert style helper: find by unique key, update or create if missing
$account = User::updateOrCreate(
    ['email' => 'dev@zerophp.com'],
    ['name' => 'Ada Lovelace']
); // lookup attributes merge with updates; conflicting keys take the new value

// Retrieve the first matching profile or create it with sensible defaults
$profile = Profile::findOrCreate(
    ['user_id' => $account->id],
    ['nickname' => 'adal']
);
```

`save()` persists the current state (insert or update). `refresh()` reloads the model from the database and resets dirty tracking.

## Relationships

Relationship helpers live on the model base class and yield `Relation` objects with fluent APIs.

```php
class Post extends Model
{
    protected function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

class User extends Model
{
    protected function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    protected function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }
}
```

Supported relations:

- `hasOne(Related::class, $foreignKey = null, $localKey = null)`
- `hasMany(Related::class, $foreignKey = null, $localKey = null)`
- `belongsTo(Related::class, $foreignKey = null, $ownerKey = null)`
- `belongsToMany(Related::class, $pivotTable = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null)`

Many-to-many relations expose pivot helpers:

```php
$user = User::find(1);
$user?->roles()->attach([3, 5]);
$user?->roles()->sync([3 => ['granted_by' => 2]]);
$user?->roles()->detach(5);
```

Relations cache their results; use `relationLoaded()`, `getRelation()`, and `setRelation()` for manual management during custom eager loading.

## Lifecycle Hooks

Override any of these optional methods on your model to run domain logic around persistence events:

- `beforeCreate` / `afterCreate`
- `beforeUpdate` / `afterUpdate`
- `beforeSave` / `afterSave`
- `beforeDelete` / `afterDelete`
- `beforeRestore` / `afterRestore`

Hooks are no-ops on the base class; declaring them in your model triggers them automatically when the corresponding action runs.

## Summary

- Extend `Zero\Lib\Model` for active-record style access with minimal magic.
- Use `Model::query()` for fluent DBML querying and lean on `with`, `withCount`, `whereHas`, and soft-delete scopes as needed.
- Manage attributes via `fillable`, timestamps, and optional UUID generation.
- Define relationships with `hasOne`, `hasMany`, `belongsTo`, and `belongsToMany`, including pivot helpers.
- Leverage lifecycle hooks and `refresh()` to keep long-lived objects in sync.

The model layer stays deliberately small—if you need something more bespoke, drop to DBML or write repository classes that combine both layers.
