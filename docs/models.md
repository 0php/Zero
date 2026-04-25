# Models

`Zero\Lib\Model` is a lightweight active-record layer on top of [DBML](dbml.md). Extend it under `App\Models` to map a table, hydrate records, and declare relationships.

```php
namespace App\Models;

use Zero\Lib\Model;

class User extends Model
{
    protected static string $table = 'users';
    protected static array $fillable = ['name', 'email', 'password'];
    protected static array $hidden = ['password'];
    protected static bool $timestamps = true;
}
```

Implementation: [`Model.php`](../core/libraries/Model/Model.php), [`ModelQuery.php`](../core/libraries/Model/ModelQuery.php), [`Concerns/InteractsWithRelations.php`](../core/libraries/Model/Concerns/InteractsWithRelations.php).

`__call` / `__callStatic` forward unknown methods to a fresh `ModelQuery`, so `User::where(...)`, `User::orderBy(...)`, etc., all work.

---

## Static query entry points

### `Model::query(): ModelQuery`
Get a fresh query builder bound to the model.
```php
$users = User::query()->where('active', 1)->get();
```

### `Model::with(array|string $relations): ModelQuery`
Eager-load relations.
```php
$posts = Post::with('author')->get();
$posts = Post::with(['author', 'comments'])->get();
```

### `Model::withCount(array|string $relations): ModelQuery`
Add a `<relation>_count` attribute.
```php
$users = User::withCount('posts')->get();
$users[0]->posts_count; // int
```

### `Model::all(): array`
Fetch every row.
```php
$users = User::all();
```

### `Model::find(mixed $id): ?static`
Find by primary key.
```php
$user = User::find(42);
```

### `Model::create(array $attributes): static`
Insert a new row.
```php
$user = User::create([
    'name'  => 'Tofik',
    'email' => 'tofik@example.test',
]);
```

### `Model::updateOrCreate(array $attributes, array $values = []): static`
```php
$user = User::updateOrCreate(
    ['email' => 'tofik@example.test'],
    ['name'  => 'Tofik H.']
);
```

### `Model::findOrCreate(array $attributes, array $values = []): static`
Same as `updateOrCreate` but only insert when not found (no update on the existing row).
```php
$user = User::findOrCreate(['email' => 'tofik@example.test']);
```

### `Model::paginate(int $perPage = 15, int $page = 1): Paginator`
```php
$users = User::paginate(20, page: (int) request('page', 1));
foreach ($users as $user) { /* ... */ }
$users->total(); // total count
```

### `Model::simplePaginate(int $perPage = 15, int $page = 1): Paginator`
Cheaper than `paginate()` — no total count.
```php
$users = User::simplePaginate(15);
```

---

## Instance methods

### `->save(): bool`
Persist a new or modified instance.
```php
$user = new User(['name' => 'Tofik']);
$user->email = 'tofik@example.test';
$user->save();
```

### `->update(?array $attributes = null): bool`
Mass-assign and save.
```php
$user->update(['name' => 'New Name']);
```

### `->delete(): bool`
Soft delete when configured; hard delete otherwise.
```php
$user->delete();
```

### `->forceDelete(): bool`
Hard delete even when soft deletes are enabled.
```php
$user->forceDelete();
```

### `->restore(): bool`
Restore a soft-deleted record.
```php
$user->restore();
```

### `->trashed(): bool`
```php
if ($user->trashed()) { /* ... */ }
```

### `->usesSoftDeletes(): bool`
True when the model has a `deleted_at` column / config.

### `->getDeletedAtColumn(): string`
Defaults to `deleted_at`.

### `->refresh(): static`
Reload the row from the database.
```php
$user->refresh();
```

### `->exists(): bool`
True after `save()` / `find()`.
```php
$user->exists(); // true
```

### `->getKey(): mixed`
The primary key value.
```php
$user->getKey(); // 42
```

### `->fill(array $attributes): static`
Mass-assign respecting `$fillable`.
```php
$user->fill(['name' => 'Tofik', 'email' => 'a@b'])->save();
```

### `->forceFill(array $attributes): static`
Bypass `$fillable`.
```php
$user->forceFill(['admin' => true])->save();
```

### `->getAttribute(string $key): mixed` / `->hasAttribute(string $key): bool`
```php
$user->getAttribute('name');
$user->hasAttribute('email'); // true
```

### `->getAttributes(): array` / `->getOriginal(): array`
```php
$user->getAttributes();  // current
$user->getOriginal();    // attributes when loaded
```

### `->isDirty(): bool`
```php
$user->name = 'X';
$user->isDirty(); // true
```

### `->getPrimaryKey(): string`
Default `'id'`.

### `->toArray(): array` / `->jsonSerialize(): array`
Convert to array (respects `$hidden`).
```php
$user->toArray();
json_encode($user); // jsonSerialize()
```

### Property access (magic)

`__get`, `__set`, `__isset`, `__unset` proxy to attributes/relations.
```php
$user->name;             // attribute
$user->posts;            // loaded relation (HasMany → array)
isset($user->email);     // bool
unset($user->cached);    // remove an attribute
```

---

## Relationships

Define on the model. The framework infers foreign keys from snake-case class names; override when needed.

### `hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne`
```php
class User extends Model
{
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}

$user->profile; // Profile|null
```

### `hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany`
```php
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

$user->posts; // array<Post>
```

### `belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo`
```php
class Post extends Model
{
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

$post->author; // User|null
```

### `belongsToMany(...)`
Many-to-many through a pivot table.
```php
class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }
}
```

### `->relationLoaded(string $key): bool`
```php
$user->relationLoaded('posts');
```

### `->getRelation(string $key): mixed` / `->setRelation($key, $value): static` / `->getRelations(): array`
Manually inspect or seed loaded relations.
```php
$user->setRelation('posts', $cachedPosts);
$user->getRelation('posts');
```

---

## ModelQuery

`ModelQuery` is the chainable builder. `__call` forwards any DBML method (`where`, `whereIn`, `orderBy`, `select`, `join`, `limit`, …) to the underlying [DBML](dbml.md) query — see that doc for the full set.

### `->with(array|string $relations): self`
```php
$posts = Post::query()->with(['author', 'comments'])->get();
```

### `->withCount(array|string $relations): self`
```php
$users = User::query()->withCount('posts')->get();
```

### `->whereHas(string $relation, ?Closure $callback = null): self`
```php
$activeAuthors = User::query()
    ->whereHas('posts', fn ($q) => $q->where('published', 1))
    ->get();
```

### `->orWhereHas(string $relation, ?Closure $callback = null): self`
```php
$query->whereHas('posts')->orWhereHas('comments');
```

### `->whereDoesntHave(string $relation, ?Closure $callback = null): self`
```php
$inactive = User::query()->whereDoesntHave('posts')->get();
```

### `->orWhereDoesntHave(string $relation, ?Closure $callback = null): self`

### Soft-delete scopes

#### `->withTrashed(): self`
Include soft-deleted rows.
```php
$all = User::query()->withTrashed()->get();
```

#### `->onlyTrashed(): self`
```php
$trash = User::query()->onlyTrashed()->get();
```

#### `->withoutTrashed(): self`
The default; useful when overriding a previous scope.
```php
$query->withTrashed()->where('active', 1)->withoutTrashed();
```

### Terminal methods

#### `->get(array|string|DBMLExpression $columns = []): array`
```php
$users = User::query()->where('active', 1)->get(['id', 'email']);
```

#### `->first(array|string|DBMLExpression $columns = []): ?Model`
```php
$user = User::query()->where('email', $email)->first();
```

#### `->find(mixed $id, $columns = []): ?Model`
```php
$user = User::query()->find(42, ['id', 'email']);
```

#### `->updateOrCreate(array $attributes, array $values = []): Model`
```php
User::query()->updateOrCreate(
    ['email' => $email],
    ['name' => $name]
);
```

#### `->findOrCreate(array $attributes, array $values = []): Model`
```php
User::query()->findOrCreate(['email' => $email]);
```

#### `->count(string $column = '*'): int`
```php
$active = User::query()->where('active', 1)->count();
```

#### `->exists(): bool`
```php
if (User::query()->where('email', $email)->exists()) { /* ... */ }
```

#### `->pluck(string $column, ?string $key = null): array`
```php
$emails = User::query()->pluck('email');               // ['a@b', 'c@d']
$emails = User::query()->pluck('email', 'id');         // [1 => 'a@b', 2 => 'c@d']
```

#### `->value(string $column): mixed`
```php
$email = User::query()->where('id', 42)->value('email');
```

#### `->paginate(int $perPage = 15, int $page = 1): Paginator` / `->simplePaginate(...)`
```php
$users = User::query()->where('active', 1)->paginate(20);
```

#### `->delete(): int` / `->forceDelete(): int`
```php
User::query()->where('active', 0)->delete();        // soft if enabled
User::query()->where('active', 0)->forceDelete();   // always hard
```

### Inspection

#### `->toBase(): DBML`
Drop down to the raw DBML builder.
```php
$builder = User::query()->where('active', 1)->toBase();
```

#### `->toSql(): string` / `->getBindings(): array`
Useful for logging/debugging.
```php
$sql      = User::query()->where('active', 1)->toSql();
$bindings = User::query()->where('active', 1)->getBindings();
```

---

## Conventions

- **Table name**: `static $table` (defaults to plural snake-case of the class).
- **Primary key**: `static $primaryKey = 'id'`.
- **Mass assignment**: `static $fillable = [...]`.
- **Hidden attributes**: `static $hidden = [...]` (omitted from `toArray`/JSON).
- **Casts**: `static $casts = ['payload' => 'array']`.
- **Timestamps**: `static $timestamps = true` enables `created_at`/`updated_at` auto-fill.
- **Soft deletes**: add a `deleted_at` column and set `static $softDeletes = true`.

See [dbml.md](dbml.md) for the full query builder surface that `ModelQuery` delegates to.
