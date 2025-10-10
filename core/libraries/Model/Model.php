<?php

declare(strict_types=1);

namespace Zero\Lib;

use Closure;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionMethod;
use RuntimeException;
use Zero\Lib\DB\DBML;
use Zero\Lib\DB\DBMLExpression;
use Zero\Lib\Model\Concerns\InteractsWithRelations;
use Zero\Lib\Model\ModelQuery;
use Zero\Lib\Support\Paginator;
use Zero\Lib\Support\Str;

/**
 * Minimalist active-record style model built on top of the DBML query builder.
 *
 * The base model provides conveniences inspired by Laravel's Eloquent such as
 * mass-assignment, timestamp management, soft-delete opt-in, and fluent query
 * access, while remaining dependency-free. Extend this class within
 * `App\Models` to create strongly-typed representations of database tables.
 * Enable soft deletes by setting `protected bool $softDeletes = true;` on the
 * child model and ensuring the table includes a nullable `deleted_at` column.
 */
class Model implements JsonSerializable
{
    use InteractsWithRelations;

    /**
     * Explicit table name. When null we derive the table from the class name.
     */
    protected ?string $table = null;

    /**
     * Name of the primary key column.
     */
    protected string $primaryKey = 'id';

    /**
     * Indicates whether the primary key is auto-incrementing.
     */
    protected bool $incrementing = true;

    /**
     * Automatically assign a UUID when inserting.
     */
    protected bool $usesUuid = false;

    /**
     * Column that should receive the generated UUID (defaults to the primary key).
     */
    protected ?string $uuidColumn = null;

    /**
     * List of attributes that are mass assignable. Empty array permits all.
     *
     * @var string[]
     */
    protected array $fillable = [];

    /**
     * Whether created_at/updated_at columns should be maintained automatically.
     */
    protected bool $timestamps = true;

    /**
     * Toggle soft delete behaviour; set to true on child models to opt-in.
     */
    protected bool $softDeletes = false;

    /**
     * Column name that stores the soft delete timestamp.
     */
    protected string $deletedAtColumn = 'deleted_at';

    /**
     * Column names used for timestamps when enabled.
     */
    protected string $createdAtColumn = 'created_at';
    protected string $updatedAtColumn = 'updated_at';

    /**
     * Internal attribute bag representing the current model state.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Snapshot of attributes retrieved from the database for dirty tracking.
     *
     * @var array<string, mixed>
     */
    protected array $original = [];

    /**
     * Eager-loaded relationship data keyed by relation name.
     *
     * @var array<string, mixed>
     */
    protected array $relations = [];

    /**
     * Indicates whether this model exists in the database.
     */
    protected bool $exists = false;

    public function __construct(array $attributes = [], bool $exists = false)
    {
        $this->exists = $exists;

        if ($exists) {
            $this->forceFill($attributes);
        } else {
            $this->fill($attributes);
        }

        $this->syncOriginal();
    }

    /**
     * Begin a fluent query for the model's table.
     */
    public static function query(): ModelQuery
    {
        return (new static())->newQuery();
    }

    public static function with(array|string $relations): ModelQuery
    {
        return static::query()->with($relations);
    }

    public static function withCount(array|string $relations): ModelQuery
    {
        return static::query()->withCount($relations);
    }

    /**
     * Retrieve all rows for the model.
     *
     * @return static[]
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * Paginate model results.
     */
    public static function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        return static::query()->paginate($perPage, $page);
    }

    /**
     * Simple pagination without executing an additional count query.
     */
    public static function simplePaginate(int $perPage = 15, int $page = 1): Paginator
    {
        return static::query()->simplePaginate($perPage, $page);
    }

    /**
     * Find a model by its primary key.
     */
    public static function find(mixed $id): ?static
    {
        $result = static::query()->find($id);

        return $result instanceof static ? $result : null;
    }

    /**
     * Persist a new model instance with the provided attributes.
     */
    public static function create(array $attributes): static
    {
        $model = new static();
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    /**
     * Update the first matching record or create it with the provided values.
     */
    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        /** @var static $model */
        $model = static::query()->updateOrCreate($attributes, $values);

        return $model;
    }

    /**
     * Retrieve the first matching record or create it with the provided values.
     */
    public static function findOrCreate(array $attributes, array $values = []): static
    {
        /** @var static $model */
        $model = static::query()->findOrCreate($attributes, $values);

        return $model;
    }

    /**
     * Determine if the model exists in the database.
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Get the value of the primary key.
     */
    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    /**
     * Persist the model to the database (insert or update).
     */
    public function save(): bool
    {
        return $this->exists ? $this->performUpdate() : $this->performInsert();
    }

    /**
     * Mass-assign the provided attributes and persist the model.
     */
    public function update(?array $attributes = null): bool
    {
        if(!is_null($attributes)) {
            $this->fill($attributes);   
        }

        return $this->save();
    }

    /**
     * Delete the model from the database.
     */
    public function delete(): bool
    {
        if (! $this->exists) {
            return false;
        }

        if ($this->usesSoftDeletes()) {
            return $this->performSoftDelete();
        }

        return $this->performHardDelete();
    }

    /**
     * Force a hard delete even when soft deletes are enabled.
     */
    public function forceDelete(): bool
    {
        if (! $this->exists) {
            return false;
        }

        return $this->performHardDelete();
    }

    /**
     * Restore a soft deleted model instance.
     */
    public function restore(): bool
    {
        if (! $this->usesSoftDeletes()) {
            return false;
        }

        if (! $this->trashed()) {
            return true;
        }

        $this->fireHook('beforeRestore');

        $this->attributes[$this->getDeletedAtColumn()] = null;

        $restored = $this->performUpdate();

        if ($restored) {
            $this->fireHook('afterRestore');
        }

        return $restored;
    }

    /**
     * Determine whether the model instance has been soft deleted.
     */
    public function trashed(): bool
    {
        if (! $this->usesSoftDeletes()) {
            return false;
        }

        return $this->getAttribute($this->getDeletedAtColumn()) !== null;
    }

    /**
     * Expose whether the model uses soft deletes.
     */
    public function usesSoftDeletes(): bool
    {
        return $this->softDeletes;
    }

    /**
     * Resolve the column name that stores the soft delete timestamp.
     */
    public function getDeletedAtColumn(): string
    {
        return $this->deletedAtColumn;
    }

    /**
     * Reload the model state from the database.
     */
    public function refresh(): static
    {
        if (! $this->exists) {
            return $this;
        }

        $key = $this->getKey();

        if ($key === null) {
            throw new RuntimeException('Cannot refresh a model without a primary key value.');
        }

        $query = static::query();

        if ($this->usesSoftDeletes()) {
            $query = $query->withTrashed();
        }

        $fresh = $query->where($this->getPrimaryKey(), $key)
            ->first();

        if ($fresh instanceof static) {
            $this->forceFill($fresh->toArray());
            $this->exists = true;
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes respecting the fillable whitelist.
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Assign attributes directly, ignoring fillable restrictions.
     */
    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    /**
     * Retrieve an attribute value by key.
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Determine if a given attribute or relation is set.
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Determine whether the given relation has been loaded.
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Retrieve a previously loaded relation value.
     */
    public function getRelation(string $key): mixed
    {
        return $this->relations[$key] ?? null;
    }

    /**
     * Set a relation value on the model.
     */
    public function setRelation(string $key, mixed $value): static
    {
        $this->relations[$key] = $value;

        return $this;
    }

    /**
     * Retrieve all loaded relations.
     *
     * @return array<string, mixed>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Determine if the model has any dirty (changed) attributes.
     */
    public function isDirty(): bool
    {
        return ! empty($this->getDirty());
    }

    /**
     * Return the underlying attribute array.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the original raw attributes loaded from storage.
     */
    public function getOriginal(): array
    {
        return $this->original;
    }

    /**
     * Accessor for the primary key column name.
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    protected function ensureUuidKey(): void
    {
        if (! $this->usesUuid) {
            return;
        }

        $column = $this->uuidColumn ?? $this->primaryKey;

        if (! $column) {
            return;
        }

        $current = $this->attributes[$column] ?? null;

        if ($current === null || $current === '') {
            $this->attributes[$column] = Str::uuid();
        }
    }

    protected function singularTableName(string $table): string
    {
        if (str_ends_with($table, 'ies')) {
            return substr($table, 0, -3) . 'y';
        }

        if (preg_match('/(xes|ses|zes|ches|shes)$/', $table)) {
            return substr($table, 0, -2);
        }

        if (str_ends_with($table, 's')) {
            return substr($table, 0, -1);
        }

        return $table;
    }

    protected function pluralizeTableName(string $table): string
    {
        if (str_ends_with($table, 'y')) {
            return substr($table, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/', $table)) {
            return $table . 'es';
        }

        if (!str_ends_with($table, 's')) {
            return $table . 's';
        }

        return $table;
    }

    /**
     * Lifecycle hooks. Override in child classes as needed.
     */
    protected function beforeCreate(): void {}
    protected function afterCreate(): void {}
    protected function beforeUpdate(): void {}
    protected function afterUpdate(): void {}
    protected function beforeSave(): void {}
    protected function afterSave(): void {}
    protected function beforeDelete(): void {}
    protected function afterDelete(): void {}
    protected function beforeRestore(): void {}
    protected function afterRestore(): void {}

    private function fireHook(string $method): void
    {
        if (!method_exists($this, $method)) {
            return;
        }

        $reflection = new ReflectionMethod($this, $method);

        if ($reflection->getDeclaringClass()->getName() === self::class) {
            return; // Skip base no-op definitions
        }

        $reflection->setAccessible(true);
        $reflection->invoke($this);
    }

    public function __get(string $key): mixed
    {
        if (property_exists($this, $key)) {
            return $this->{$key};
        }

        if ($this->hasAttribute($key)) {
            return $this->attributes[$key];
        }

        return $this->getRelationValue($key);
    }

    public function __set(string $key, mixed $value): void
    {
        if (property_exists($this, $key)) {
            $this->{$key} = $value;

            return;
        }

        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        if (property_exists($this, $key)) {
            return isset($this->{$key});
        }

        if ($this->hasAttribute($key)) {
            return isset($this->attributes[$key]);
        }

        return $this->relationLoaded($key) && isset($this->relations[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }
}
