<?php

declare(strict_types=1);

namespace Zero\Lib\Model\Concerns;

use RuntimeException;
use Zero\Lib\DB\DBML;
use Zero\Lib\Model as BaseModel;
use Zero\Lib\Model\ModelQuery;
use Zero\Lib\Model\Relation;
use Zero\Lib\Model\Relations\BelongsTo;
use Zero\Lib\Model\Relations\BelongsToMany;
use Zero\Lib\Model\Relations\HasMany;
use Zero\Lib\Model\Relations\HasOne;

trait InteractsWithRelations
{
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

        $baseQuery = $instance->newQuery();
        $query = clone $baseQuery;

        if ($localValue !== null) {
            $query->where($foreignKey, $localValue);
        } else {
            $query->whereRaw('1 = 0');
        }

        $query->limit(1);

        return new HasOne($query, $baseQuery, $this, $instance, $foreignKey, $localKey, $localValue);
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

        $baseQuery = $instance->newQuery();
        $query = clone $baseQuery;

        if ($localValue !== null) {
            $query->where($foreignKey, $localValue);
        } else {
            $query->whereRaw('1 = 0');
        }

        return new HasMany($query, $baseQuery, $this, $instance, $foreignKey, $localKey, $localValue);
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

        $baseQuery = $instance->newQuery();
        $query = clone $baseQuery;

        if ($foreignValue !== null) {
            $query->where($ownerKey, $foreignValue);
        } else {
            $query->whereRaw('1 = 0');
        }

        $query->limit(1);

        $relationName = $this->guessRelationName();

        return new BelongsTo($query, $baseQuery, $this, $instance, $foreignKey, $ownerKey, $foreignValue, $relationName);
    }

    /**
     * Define a many-to-many relationship.
     */
    protected function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): BelongsToMany {
        $instance = $this->newRelatedInstance($related);
        $relationName = $this->guessRelationName();

        $table ??= $this->guessBelongsToManyPivotTable($instance);
        $foreignPivotKey ??= $this->guessBelongsToManyForeignKey();
        $relatedPivotKey ??= $instance->guessBelongsToManyForeignKey();
        $parentKey ??= $this->getPrimaryKey();
        $relatedKey ??= $instance->getPrimaryKey();
        $parentValue = $this->getAttribute($parentKey);

        $baseQuery = $instance->newQuery()
            ->select($instance->getTable() . '.*')
            ->join(
                $table,
                $instance->getTable() . '.' . $relatedKey,
                '=',
                $table . '.' . $relatedPivotKey
            );

        $query = clone $baseQuery;

        if ($parentValue !== null) {
            $query->where($table . '.' . $foreignPivotKey, $parentValue);
        } else {
            $query->whereRaw('1 = 0');
        }

        return new BelongsToMany(
            $query,
            $baseQuery,
            $this,
            $instance,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $parentValue,
            $relationName
        );
    }

    /**
     * Instantiate a related model instance.
     */
    protected function newRelatedInstance(string $related): BaseModel
    {
        /** @var BaseModel $instance */
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
        $this->fireHook('beforeCreate');
        $this->fireHook('beforeSave');

        $this->ensureUuidKey();

        $attributes = $this->attributes;
        $this->applyTimestampsForInsert($attributes);

        $id = $this->newBaseQuery()->insert($attributes);

        $this->attributes = array_merge($this->attributes, $attributes);

        if ($this->incrementing && $this->primaryKey && ! isset($this->attributes[$this->primaryKey])) {
            $this->attributes[$this->primaryKey] = $id;
        }

        $this->exists = true;
        $this->syncOriginal();

        $this->fireHook('afterCreate');
        $this->fireHook('afterSave');

        return true;
    }

    /**
     * Update the database record with dirty attributes.
     */
    protected function performUpdate(): bool
    {
        $dirty = $this->getDirty();

        if ($dirty === []) {
            return true;
        }

        $this->fireHook('beforeUpdate');
        $this->fireHook('beforeSave');

        $this->applyTimestampsForUpdate($dirty);

        if (array_key_exists($this->primaryKey, $dirty)) {
            unset($dirty[$this->primaryKey]);
        }

        if ($dirty === []) {
            $this->fireHook('afterUpdate');
            $this->fireHook('afterSave');
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
            $this->fireHook('afterUpdate');
            $this->fireHook('afterSave');
        }

        return (bool) $affected;
    }

    /**
     * Perform the soft delete update.
     */
    protected function performSoftDelete(): bool
    {
        $key = $this->getKey();

        if ($key === null) {
            throw new RuntimeException('Cannot delete a model without a primary key value.');
        }

        $timestamp = $this->freshTimestampString();
        $columns = [
            $this->getDeletedAtColumn() => $timestamp,
        ];

        if ($this->usesTimestamps()) {
            $columns[$this->updatedAtColumn] = $timestamp;
        }

        $this->fireHook('beforeDelete');

        $deleted = $this->newBaseQuery()
            ->where($this->getPrimaryKey(), $key)
            ->update($columns);

        if ($deleted) {
            $this->forceFill($columns);
            $this->fireHook('afterDelete');
            $this->syncOriginal();
        }

        return (bool) $deleted;
    }

    /**
     * Perform a hard delete against the underlying table.
     */
    protected function performHardDelete(): bool
    {
        $key = $this->getKey();

        if ($key === null) {
            throw new RuntimeException('Cannot delete a model without a primary key value.');
        }

        $this->fireHook('beforeDelete');

        $deleted = $this->newBaseQuery()
            ->where($this->getPrimaryKey(), $key)
            ->delete();

        if ($deleted) {
            $this->exists = false;

            if ($this->usesSoftDeletes()) {
                $this->attributes[$this->getDeletedAtColumn()] = null;
            }

            $this->fireHook('afterDelete');
            $this->syncOriginal();
        }

        return (bool) $deleted;
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
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($trace as $frame) {
            $method = $frame['function'] ?? null;
            $class = $frame['class'] ?? null;

            if (!is_string($method) || $method === '__get') {
                continue;
            }

            if ($class === self::class) {
                continue;
            }

            return $method;
        }

        return null;
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

    /**
     * Determine the default pivot table name for a many-to-many relationship.
     */
    protected function guessBelongsToManyPivotTable(BaseModel $related): string
    {
        $segments = [
            $this->pivotSegment(),
            $related->pivotSegment(),
        ];

        sort($segments, SORT_STRING);

        if (isset($segments[1])) {
            $segments[1] = $this->pluralizeTableName($segments[1]);
        }

        return implode('_', $segments);
    }

    protected function pivotSegment(): string
    {
        return $this->singularTableName($this->getTable());
    }

    /**
     * Guess the foreign key column name for a many-to-many pivot table.
     */
    protected function guessBelongsToManyForeignKey(): string
    {
        return $this->snakeCase($this->classBaseName()) . '_id';
    }
}
