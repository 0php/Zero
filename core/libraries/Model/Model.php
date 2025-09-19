<?php

declare(strict_types=1);

namespace Zero\Lib;

use JsonSerializable;
use RuntimeException;
use Zero\Lib\DB\DBML;
use Zero\Lib\Support\Paginator;

/**
 * Minimalist active-record style model built on top of the DBML query builder.
 *
 * The base model provides conveniences inspired by Laravel's Eloquent such as
 * mass-assignment, timestamp management, and fluent query access, while
 * remaining dependency-free. Extend this class within `App\Models` to create
 * strongly-typed representations of database tables.
 */
class Model implements JsonSerializable
{
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
     * Delete the model from the database.
     */
    public function delete(): bool
    {
        if (! $this->exists) {
            return false;
        }

        $key = $this->getKey();

        if ($key === null) {
            throw new RuntimeException('Cannot delete a model without a primary key value.');
        }

        $deleted = $this->newBaseQuery()
            ->where($this->getPrimaryKey(), $key)
            ->delete();

        if ($deleted) {
            $this->exists = false;
        }

        return (bool) $deleted;
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

        $fresh = static::query()
            ->where($this->getPrimaryKey(), $key)
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

    /**
     * Start a new model query builder instance.
     */
    public function newQuery(): ModelQuery
    {
        return $this->newModelBuilder($this->newBaseQuery());
    }

    /**
     * Create a builder tied to the model class around the supplied DBML builder.
     */
    protected function newModelBuilder(DBML $query): ModelQuery
    {
        return new ModelQuery(static::class, $query);
    }

    /**
     * Create a base DBML query for the model's table.
     */
    protected function newBaseQuery(): DBML
    {
        return DBML::table($this->getTable());
    }

    /**
     * Define a has-one relationship.
     */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $instance = $this->newRelatedInstance($related);
        $foreignKey ??= $this->guessHasForeignKey();
        $localKey ??= $this->getPrimaryKey();
        $localValue = $this->getAttribute($localKey);

        $query = $instance->newQuery();

        if ($localValue !== null) {
            $query = $query->where($foreignKey, $localValue)->limit(1);
        } else {
            $query = $query->whereRaw('1 = 0')->limit(1);
        }

        return new HasOne($query, $this, $localValue);
    }

    /**
     * Define a has-many relationship.
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = $this->newRelatedInstance($related);
        $foreignKey ??= $this->guessHasForeignKey();
        $localKey ??= $this->getPrimaryKey();
        $localValue = $this->getAttribute($localKey);

        $query = $instance->newQuery();

        if ($localValue !== null) {
            $query = $query->where($foreignKey, $localValue);
        } else {
            $query = $query->whereRaw('1 = 0');
        }

        return new HasMany($query, $this, $localValue);
    }

    /**
     * Define a belongs-to relationship.
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = $this->newRelatedInstance($related);
        $foreignKey ??= $this->guessBelongsToForeignKey($related);
        $ownerKey ??= $instance->getPrimaryKey();
        $foreignValue = $this->getAttribute($foreignKey);

        $query = $instance->newQuery();

        if ($foreignValue !== null) {
            $query = $query->where($ownerKey, $foreignValue)->limit(1);
        } else {
            $query = $query->whereRaw('1 = 0')->limit(1);
        }

        $relationName = $this->guessRelationName();

        return new BelongsTo($query, $this, $foreignKey, $ownerKey, $foreignValue, $relationName);
    }

    /**
     * Instantiate a related model instance.
     */
    protected function newRelatedInstance(string $related): Model
    {
        /** @var Model $instance */
        $instance = new $related();

        return $instance;
    }

    /**
     * Resolve the table name, defaulting to a snake_cased plural of the class.
     */
    public function getTable(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        return $this->table = $this->guessTableName();
    }

    /**
     * Determine if the given attribute is mass assignable.
     */
    protected function isFillable(string $key): bool
    {
        if ($this->fillable === []) {
            return true;
        }

        return in_array($key, $this->fillable, true);
    }

    /**
     * Persist a new record for the model.
     */
    protected function performInsert(): bool
    {
        $attributes = $this->attributes;
        $this->applyTimestampsForInsert($attributes);

        $id = $this->newBaseQuery()->insert($attributes);

        $this->attributes = array_merge($this->attributes, $attributes);

        if ($this->incrementing && $this->primaryKey && ! isset($this->attributes[$this->primaryKey])) {
            $this->attributes[$this->primaryKey] = $id;
        }

        $this->exists = true;
        $this->syncOriginal();

        return true;
    }

    /**
     * Update the database record with dirty attributes.
     */
    protected function performUpdate(): bool
    {
        $dirty = $this->getDirty();
        $this->applyTimestampsForUpdate($dirty);

        if ($dirty === []) {
            return true;
        }

        if (array_key_exists($this->primaryKey, $dirty)) {
            unset($dirty[$this->primaryKey]);
        }

        if ($dirty === []) {
            return true;
        }

        $key = $this->getKey();

        if ($key === null) {
            throw new RuntimeException('Cannot update a model without a primary key value.');
        }

        $affected = $this->newBaseQuery()
            ->where($this->getPrimaryKey(), $key)
            ->update($dirty);

        if ($affected) {
            $this->forceFill($dirty);
            $this->syncOriginal();
        }

        return (bool) $affected;
    }

    /**
     * Apply timestamp columns when inserting rows.
     */
    protected function applyTimestampsForInsert(array &$attributes): void
    {
        if (! $this->usesTimestamps()) {
            return;
        }

        $timestamp = $this->freshTimestampString();

        $attributes[$this->createdAtColumn] = $attributes[$this->createdAtColumn] ?? $timestamp;
        $attributes[$this->updatedAtColumn] = $attributes[$this->updatedAtColumn] ?? $timestamp;
    }

    /**
     * Apply timestamp columns when updating rows.
     */
    protected function applyTimestampsForUpdate(array &$attributes): void
    {
        if (! $this->usesTimestamps()) {
            return;
        }

        $attributes[$this->updatedAtColumn] = $this->freshTimestampString();
    }

    /**
     * Determine whether timestamps are enabled for the model.
     */
    protected function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * Generate a timestamp string for persistence.
     */
    protected function freshTimestampString(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Gather the attributes that have been modified from their original values.
     *
     * @return array<string, mixed>
     */
    protected function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (! array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Snapshot the current attributes as the original state.
     */
    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Load a relationship value, caching the result on the model.
     */
    protected function getRelationValue(string $key): mixed
    {
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        if (! method_exists($this, $key)) {
            return null;
        }

        $relation = $this->{$key}();

        if ($relation instanceof Relation) {
            $results = $relation->getResults();
            $this->setRelation($key, $results);

            return $results;
        }

        return $relation;
    }

    /**
     * Derive a table name from the class name.
     */
    protected function guessTableName(): string
    {
        $base = $this->classBaseName();
        $snake = $this->snakeCase($base);

        if (! str_ends_with($snake, 's')) {
            $snake .= 's';
        }

        return $snake;
    }

    /**
     * Retrieve the class base name without namespaces.
     */
    protected function classBaseName(): string
    {
        $class = static::class;

        if (($pos = strrpos($class, '\\')) !== false) {
            return substr($class, $pos + 1);
        }

        return $class;
    }

    /**
     * Convert a string to snake_case.
     */
    protected function snakeCase(string $value): string
    {
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));

        return str_replace(' ', '_', $snake);
    }

    /**
     * Determine the name of the relationship method that invoked a relation helper.
     */
    protected function guessRelationName(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $method = $trace[1]['function'] ?? null;

        if ($method === '__get' && isset($trace[2]['function'])) {
            $method = $trace[2]['function'];
        }

        return is_string($method) ? $method : null;
    }

    /**
     * Guess the foreign key name for a belongsTo relationship.
     */
    protected function guessBelongsToForeignKey(string $related): string
    {
        $relationName = $this->guessRelationName();

        if ($relationName) {
            return $this->snakeCase($relationName) . '_id';
        }

        $base = (new $related())->classBaseName();

        return $this->snakeCase($base) . '_id';
    }

    /**
     * Guess the foreign key name for has-one or has-many relationships.
     */
    protected function guessHasForeignKey(): string
    {
        return $this->snakeCase($this->classBaseName()) . '_id';
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

/**
 * Model-aware wrapper around DBML providing hydrated results.
 */
class ModelQuery
{
    public function __construct(
        protected string $modelClass,
        protected DBML $builder
    ) {
    }

    public function __clone(): void
    {
        $this->builder = clone $this->builder;
    }

    /**
     * Proxy builder calls while maintaining fluency.
     */
    public function __call(string $name, array $arguments): mixed
    {
        $result = $this->builder->{$name}(...$arguments);

        if ($result instanceof DBML) {
            $this->builder = $result;

            return $this;
        }

        return $result;
    }

    /**
     * Execute the query and hydrate an array of models.
     *
     * @return Model[]
     */
    public function get(array|string|DBMLExpression $columns = []): array
    {
        $records = $this->builder->get($columns);

        return array_map(fn (array $attributes) => $this->newModel($attributes, true), $records);
    }

    /**
     * Fetch the first result or null.
     */
    public function first(array|string|DBMLExpression $columns = []): ?Model
    {
        $record = $this->builder->first($columns);

        if ($record === null) {
            return null;
        }

        return $this->newModel($record, true);
    }

    /**
     * Retrieve a model by its primary key.
     */
    public function find(mixed $id, array|string|DBMLExpression $columns = []): ?Model
    {
        $clone = clone $this;
        $clone->builder = $clone->builder->where($clone->primaryKey(), $id)->limit(1);

        return $clone->first($columns);
    }

    /**
     * Return the underlying DBML builder instance.
     */
    public function toBase(): DBML
    {
        return $this->builder;
    }

    /**
     * Retrieve the compiled SQL string for the current query.
     */
    public function toSql(): string
    {
        return $this->builder->toSql();
    }

    /**
     * Retrieve the parameter bindings for the current query.
     */
    public function getBindings(): array
    {
        return $this->builder->getBindings();
    }

    /**
     * Count the total results for the current query.
     */
    public function count(string $column = '*'): int
    {
        return $this->builder->count($column);
    }

    /**
     * Paginate the model results.
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $paginator = $this->builder->paginate($perPage, $page);

        $items = array_map(fn (array $attributes) => $this->newModel($attributes, true), $paginator->items());

        return new Paginator($items, $paginator->total(), $paginator->perPage(), $paginator->currentPage());
    }

    /**
     * Simple pagination without executing a count query.
     */
    public function simplePaginate(int $perPage = 15, int $page = 1): Paginator
    {
        $paginator = $this->builder->simplePaginate($perPage, $page);

        $items = array_map(fn (array $attributes) => $this->newModel($attributes, true), $paginator->items());

        return new Paginator($items, $paginator->total(), $paginator->perPage(), $paginator->currentPage());
    }

    /**
     * Determine whether any results exist for the query.
     */
    public function exists(): bool
    {
        return $this->builder->exists();
    }

    /**
     * Retrieve a list of column values.
     */
    public function pluck(string $column, ?string $key = null): array
    {
        return $this->builder->pluck($column, $key);
    }

    /**
     * Retrieve a single column value from the first row.
     */
    public function value(string $column): mixed
    {
        return $this->builder->value($column);
    }

    /**
     * Hydrate a new model instance with the given attributes.
     */
    protected function newModel(array $attributes, bool $exists): Model
    {
        /** @var Model $model */
        $model = new $this->modelClass($attributes, $exists);

        return $model;
    }

    protected function primaryKey(): string
    {
        $model = new $this->modelClass();

        return $model->getPrimaryKey();
    }
}

/**
 * Base relationship type responsible for retrieving related models.
 */
abstract class Relation
{
    public function __construct(
        protected ModelQuery $query,
        protected Model $parent
    ) {
    }

    public function getQuery(): ModelQuery
    {
        return $this->query;
    }

    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Retrieve the relationship results.
     */
    abstract public function getResults(): mixed;
}

/**
 * Represents a has-many relationship.
 */
class HasMany extends Relation
{
    public function __construct(ModelQuery $query, Model $parent, protected mixed $localValue)
    {
        parent::__construct($query, $parent);
    }

    public function getResults(): array
    {
        if ($this->localValue === null) {
            return [];
        }

        return $this->query->get();
    }
}

/**
 * Represents a has-one relationship.
 */
class HasOne extends Relation
{
    public function __construct(ModelQuery $query, Model $parent, protected mixed $localValue)
    {
        parent::__construct($query, $parent);
    }

    public function getResults(): ?Model
    {
        if ($this->localValue === null) {
            return null;
        }

        $result = $this->query->first();

        return $result instanceof Model ? $result : null;
    }
}

/**
 * Represents a belongs-to relationship.
 */
class BelongsTo extends Relation
{
    public function __construct(
        ModelQuery $query,
        Model $parent,
        protected string $foreignKey,
        protected string $ownerKey,
        protected mixed $foreignValue,
        protected ?string $relationName = null
    ) {
        parent::__construct($query, $parent);
    }

    public function getResults(): ?Model
    {
        if ($this->foreignValue === null) {
            return null;
        }

        $result = $this->query->first();

        return $result instanceof Model ? $result : null;
    }

    /**
     * Associate the parent model with the given instance.
     */
    public function associate(Model $model): Model
    {
        $this->parent->{$this->foreignKey} = $model->{$this->ownerKey};
        if ($this->relationName) {
            $this->parent->setRelation($this->relationName, $model);
        }

        return $this->parent;
    }

    /**
     * Dissociate the parent model from the related instance.
     */
    public function dissociate(): Model
    {
        $this->parent->{$this->foreignKey} = null;
        if ($this->relationName) {
            $this->parent->setRelation($this->relationName, null);
        }

        return $this->parent;
    }
}
