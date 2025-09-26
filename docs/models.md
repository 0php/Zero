# Models

The base `Zero\Lib\Model` class brings an active-record style API on top of the DBML query builder. Extend it inside `App\Models` to describe your domain objects and interact with the database using expressive, fluent methods.

## Defining a Model

```php
namespace App\Models;

use Zero\Lib\Model;

class User extends Model
{
    protected array $fillable = ['name', 'email', 'password'];
}
```

- The default table name is derived from the class (`User` → `users`). Override the `$table` property for custom names.
- The default primary key is `id`. Change `$primaryKey` if your schema differs.
- Timestamps (`created_at`, `updated_at`) are managed automatically; set `protected bool $timestamps = false;` to disable.
- Opt into soft deletes per model by adding `protected bool $softDeletes = true;` and including a nullable `deleted_at` column in the table schema.

## Querying

Models expose a fluent query builder that hydrates results back into model instances:

```php
$users = User::query()
    ->where('active', 1)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get(); // returns an array of User models

$user = User::find(5); // returns null if not found

// Eager load relationships (including nested relations)
$users = User::with(['posts' => function ($query) {
        $query->where('published', true);
    }, 'posts.comments'])
    ->orderBy('name')
    ->get();

// Append relation counts alongside models
$authors = User::withCount('posts')->get();
foreach ($authors as $author) {
    echo $author->name . ' has ' . $author->posts_count . ' posts';
}

// You can chain both helpers together
$users = User::with('roles')->withCount(['roles' => 'roles_total'])->get();
echo $users[0]->roles_total; // hydrated count alias
```

Use `toBase()` to access the underlying DBML builder when you need low-level control (e.g., custom aggregates).

### Filtering by Relationships

Reach for `whereHas()` and `whereDoesntHave()` when you need to constrain a model query based on related records. The API mirrors Eloquent and accepts nested relation paths as well as optional callbacks to add additional constraints.

```php
use Zero\Lib\Model\ModelQuery;

// Users who have at least one published post
$authors = User::query()
    ->whereHas('posts', function (ModelQuery $query) {
        $query->where('published', true);
    })
    ->get();

// Users without any posts at all
$lurkers = User::query()->whereDoesntHave('posts')->get();

// Nested relations: authors with a post that has a 5-star review
$topRated = User::query()
    ->whereHas('posts.reviews', function (ModelQuery $query) {
        $query->where('rating', '>=', 5);
    })
    ->get();

// OR variants are available as well
$featured = User::query()
    ->whereHas('posts', fn ($q) => $q->where('featured', true))
    ->orWhereHas('roles', fn ($q) => $q->where('name', 'editor'))
    ->get();
```

Callbacks receive the underlying `ModelQuery` instance, so any of the fluent builder methods (including `with`, `withCount`, `orderBy`, etc.) are available inside. When filtering nested relations, required parameters are automatically enforced—missing route parameters will throw an exception to help you spot mistakes early.

### Pagination

```php
$paginator = User::paginate(20, $page);

foreach ($paginator->items() as $user) {
    // $user is a User model instance
}

$simple = User::simplePaginate(20, $page);
```

`paginate()` issues an additional count query to compute totals. `simplePaginate()` skips the count for performance-sensitive listings.

## Creating & Persisting

```php
$user = User::create([
    'name' => 'Ada Lovelace',
    'email' => 'ada@example.com',
]);

$user->password = password_hash('secret', PASSWORD_BCRYPT);
$user->save();
```

The base model tracks dirty attributes, updating only the fields that changed. Timestamp columns are refreshed automatically on save.

## Defining Relationships

The model layer ships with lightweight relationship helpers inspired by Eloquent.

- `hasOne(Related::class, $foreignKey = null, $localKey = null)`
- `hasMany(Related::class, $foreignKey = null, $localKey = null)`
- `belongsTo(Related::class, $foreignKey = null, $ownerKey = null)`
- `belongsToMany(Related::class, $pivotTable = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null)`

```php
class Post extends Model
{
    public function author()
    {
        return $this->belongsTo(User::class);
    }
}

class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

$posts = User::find(1)?->posts;   // Array of Post models
$author = Post::find(5)?->author; // Single User model or null
```

Relationship results are lazy-loaded and cached the first time you access them. Use `$model->relationLoaded('posts')` to check the cache, or `$model->setRelation('posts', $collection)` when eager loading manually.

### Many-to-Many

```php
class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}

class Role extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}

$user = User::find(1);
$user?->roles()->attach(3);            // insert into role_users
$user?->roles()->sync([3, 5]);         // keep only roles 3 and 5
$user?->roles()->detach(5);            // remove one role

// Add pivot metadata with timestamps
$user?->roles()->withTimestamps()->attach(7, ['granted_by' => 2]);
```

By default the framework looks for a pivot table named after the two related models using singular table names in alphabetical order with the final segment pluralised (e.g. `Role` + `User` ⇒ `role_users`). Pass the table name explicitly to `belongsToMany()` when your schema deviates from that convention. The relation proxies query builder methods, so you can chain constraints (`$user->roles()->where('roles.active', 1)->getResults()`) before fetching results.

Calling `withTimestamps()` instructs the relation to maintain `created_at` / `updated_at` (or custom column names you provide) on the pivot entries during `attach` and `sync` operations.

### UUID Primary Keys

Set the following properties on your model to generate a v4 UUID automatically when inserting new records:

```php
class ApiToken extends Model
{
    protected bool $incrementing = false;
    protected bool $usesUuid = true;
    protected ?string $uuidColumn = 'id'; // optional, defaults to the primary key
}
```

Make sure your migration creates a UUID column (see [UUID Columns](#uuid-columns)) and stores it as the primary key. The framework generates RFC 4122 compliant strings using PHP's `random_bytes`, so no external dependency is required.

## Lifecycle Hooks

Override these optional methods on your models to run domain logic around persistence events:

- `beforeCreate` / `afterCreate`
- `beforeUpdate` / `afterUpdate`
- `beforeSave` / `afterSave` (fire for both create and update flows)
- `beforeDelete` / `afterDelete`

Each hook receives the model instance via `$this`, so you can mutate attributes, enforce validation, dispatch events, or abort by throwing an exception before the write occurs. Hooks only fire when you override them—base models with no overrides incur no runtime overhead.

## Soft Deletes

Soft deletes allow you to hide records without removing them permanently. Enable the behaviour on a model by setting the `$softDeletes` flag:

```php
class Post extends Model
{
    protected bool $softDeletes = true;
}
```

Add a nullable `deleted_at` column to your migration so the framework can track the deletion timestamp:

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->timestamps();
    $table->softDeletes(); // creates a nullable deleted_at column
});
```

Once enabled, calls to `$model->delete()` (or query deletes such as `Post::query()->where(...)->delete()`) update `deleted_at` instead of issuing a hard delete. The base model also exposes helpers:

- `$model->trashed()` reports whether the instance has been soft deleted.
- `$model->restore()` clears `deleted_at` and makes the record active again.
- `$model->forceDelete()` bypasses soft deletes and removes the row permanently.

The query builder automatically excludes soft-deleted rows. Use the following scopes when you need different visibility:

```php
Post::query()->withTrashed()->get();   // includes soft-deleted posts
Post::query()->onlyTrashed()->get();   // returns only soft-deleted posts
Post::query()->withoutTrashed()->get(); // explicit default scope

// Permanently remove matching records
Post::query()->where('slug', 'obsolete')->forceDelete();
```

Relationship queries inherit the same behaviour; call `withTrashed()` on the relation query when you need to include soft-deleted children.

## UUID Columns

The schema builder ships two helpers:

```php
Schema::create('api_tokens', function (Blueprint $table) {
    $table->uuidPrimary();
    $table->foreignId('user_id')->constrained();
    $table->string('name');
    $table->timestamps();
});

Schema::table('users', function (Blueprint $table) {
    $table->uuid('external_id')->unique();
});
```

`uuid()` creates a `CHAR(36)` column, while `uuidPrimary()` marks it as the table's primary key.

## Deleting & Refreshing

```php
$user = User::find(5);

if ($user) {
    $user->delete();
}
```

Call `$model->refresh()` to reload attributes from the database after performing external updates.

## Accessing Attributes

Models behave like simple data objects—use property access, `toArray()`, or `json_encode()` thanks to the `JsonSerializable` interface. Mass assignment honours the `$fillable` whitelist, while `forceFill()` bypasses it for system-level operations.
