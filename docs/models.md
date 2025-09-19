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

## Querying

Models expose a fluent query builder that hydrates results back into model instances:

```php
$users = User::query()
    ->where('active', 1)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get(); // returns an array of User models

$user = User::find(5); // returns null if not found
```

Use `toBase()` to access the underlying DBML builder when you need low-level control (e.g., custom aggregates).

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
