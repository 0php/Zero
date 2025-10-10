<?php

declare(strict_types=1);

namespace Zero\Lib\Model;

use Closure;
use InvalidArgumentException;
use Zero\Lib\DB\DBML;
use Zero\Lib\DB\DBMLExpression;
use Zero\Lib\Model as BaseModel;
use Zero\Lib\Model\Relation;
use Zero\Lib\Model\Relations\BelongsTo;
use Zero\Lib\Model\Relations\BelongsToMany;
use Zero\Lib\Model\Relations\HasMany;
use Zero\Lib\Model\Relations\HasOne;
use Zero\Lib\Support\Paginator;

/**
 * Model-aware wrapper around DBML providing hydrated results.
 */
class ModelQuery
{
    protected bool $usesSoftDeletes = false;
    protected string $deletedAtColumn = 'deleted_at';
    protected bool $includeTrashed = false;
    protected bool $onlyTrashed = false;
    /** @var array<string, Closure|null> */
    protected array $eagerLoads = [];
    /** @var array<string, string> relation => alias */
    protected array $relationCounts = [];

    public function __construct(
        protected string $modelClass,
        protected DBML $builder
    ) {
        /** @var BaseModel $model */
        $model = new $this->modelClass();
        $this->usesSoftDeletes = $model->usesSoftDeletes();
        $this->deletedAtColumn = $model->getDeletedAtColumn();
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

    public function with(array|string $relations): self
    {
        foreach ($this->normalizeEagerRelations($relations) as $name => $constraint) {
            $this->eagerLoads[$name] = $constraint;
        }

        return $this;
    }

    public function withCount(array|string $relations): self
    {
        foreach ($this->normalizeCountRelations($relations) as $name => $alias) {
            $this->relationCounts[$name] = $alias;
        }

        return $this;
    }

    public function whereHas(string $relation, ?Closure $callback = null): self
    {
        return $this->addWhereHas($relation, $callback, false, 'AND');
    }

    public function orWhereHas(string $relation, ?Closure $callback = null): self
    {
        return $this->addWhereHas($relation, $callback, false, 'OR');
    }

    public function whereDoesntHave(string $relation, ?Closure $callback = null): self
    {
        return $this->addWhereHas($relation, $callback, true, 'AND');
    }

    public function orWhereDoesntHave(string $relation, ?Closure $callback = null): self
    {
        return $this->addWhereHas($relation, $callback, true, 'OR');
    }

    public function withTrashed(): self
    {
        if (! $this->usesSoftDeletes) {
            return $this;
        }

        $this->includeTrashed = true;
        $this->onlyTrashed = false;

        return $this;
    }

    public function onlyTrashed(): self
    {
        if (! $this->usesSoftDeletes) {
            return $this;
        }

        $this->includeTrashed = true;
        $this->onlyTrashed = true;

        return $this;
    }

    public function withoutTrashed(): self
    {
        if (! $this->usesSoftDeletes) {
            return $this;
        }

        $this->includeTrashed = false;
        $this->onlyTrashed = false;

        return $this;
    }

    /**
     * Execute the query and hydrate an array of models.
     *
     * @return BaseModel[]
     */
    public function get(array|string|DBMLExpression $columns = []): array
    {
        $records = $this->preparedBuilder()->get($columns);
        $models = array_map(fn (array $attributes) => $this->newModel($attributes, true), $records);

        return $this->hydrateModels($models);
    }

    /**
     * Fetch the first result or null.
     */
    public function first(array|string|DBMLExpression $columns = []): ?BaseModel
    {
        $record = $this->preparedBuilder()->first($columns);

        if ($record === null) {
            return null;
        }

        return $this->hydrateModel($this->newModel($record, true));
    }

    /**
     * Update the first matching model or create a new instance with the given values.
     */
    public function updateOrCreate(array $attributes, array $values = []): BaseModel
    {
        $record = $this->preparedBuilder()->updateOrCreate($attributes, $values);

        /** @var BaseModel $model */
        $model = $this->hydrateModel($this->newModel($record, true));

        return $model;
    }

    /**
     * Retrieve the first matching model or create a new instance.
     */
    public function findOrCreate(array $attributes, array $values = []): BaseModel
    {
        $record = $this->preparedBuilder()->findOrCreate($attributes, $values);

        /** @var BaseModel $model */
        $model = $this->hydrateModel($this->newModel($record, true));

        return $model;
    }

    /**
     * Retrieve a model by its primary key.
     */
    public function find(mixed $id, array|string|DBMLExpression $columns = []): ?BaseModel
    {
        $clone = clone $this;
        $clone->builder = $clone->builder->where($clone->primaryKey(), $id)->limit(1);

        return $clone->first($columns);
    }

    /**
     * Return the underlying DBML builder instance with applied scopes.
     */
    public function toBase(): DBML
    {
        return $this->preparedBuilder();
    }

    /**
     * Retrieve the compiled SQL string for the current query.
     */
    public function toSql(): string
    {
        return $this->preparedBuilder()->toSql();
    }

    /**
     * Retrieve the parameter bindings for the current query.
     */
    public function getBindings(): array
    {
        return $this->preparedBuilder()->getBindings();
    }

    /**
     * Count the total results for the current query.
     */
    public function count(string $column = '*'): int
    {
        return $this->preparedBuilder()->count($column);
    }

    /**
     * Paginate the model results.
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $paginator = $this->preparedBuilder()->paginate($perPage, $page);

        $items = array_map(fn (array $attributes) => $this->newModel($attributes, true), $paginator->items());
        $items = $this->hydrateModels($items);

        return new Paginator($items, $paginator->total(), $paginator->perPage(), $paginator->currentPage());
    }

    /**
     * Simple pagination without executing a count query.
     */
    public function simplePaginate(int $perPage = 15, int $page = 1): Paginator
    {
        $paginator = $this->preparedBuilder()->simplePaginate($perPage, $page);

        $items = array_map(fn (array $attributes) => $this->newModel($attributes, true), $paginator->items());
        $items = $this->hydrateModels($items);

        return new Paginator($items, $paginator->total(), $paginator->perPage(), $paginator->currentPage());
    }

    /**
     * Determine whether any results exist for the query.
     */
    public function exists(): bool
    {
        return $this->preparedBuilder()->exists();
    }

    /**
     * Retrieve a list of column values.
     */
    public function pluck(string $column, ?string $key = null): array
    {
        return $this->preparedBuilder()->pluck($column, $key);
    }

    /**
     * Retrieve a single column value from the first row.
     */
    public function value(string $column): mixed
    {
        return $this->preparedBuilder()->value($column);
    }

    /**
     * Delete the records matching the current query constraints.
     */
    public function delete(): int
    {
        if (! $this->usesSoftDeletes) {
            return $this->preparedBuilder()->delete();
        }

        $deleted = 0;

        foreach ($this->get() as $model) {
            if ($model->delete()) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Force delete records ignoring soft delete semantics.
     */
    public function forceDelete(): int
    {
        if (! $this->usesSoftDeletes) {
            return $this->preparedBuilder()->delete();
        }

        $deleted = 0;

        $clone = clone $this;
        $clone->withTrashed();

        foreach ($clone->get() as $model) {
            if ($model->forceDelete()) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Hydrate a new model instance with the given attributes.
     */
    protected function newModel(array $attributes, bool $exists): BaseModel
    {
        /** @var BaseModel $model */
        $model = new $this->modelClass($attributes, $exists);

        return $model;
    }

    protected function newModelInstance(): BaseModel
    {
        /** @var BaseModel $model */
        $model = new $this->modelClass();

        return $model;
    }

    protected function primaryKey(): string
    {
        $model = new $this->modelClass();

        return $model->getPrimaryKey();
    }

    /**
     * @param array<string, mixed>|string $relations
     * @return array<string, Closure|null>
     */
    protected function normalizeEagerRelations(array|string $relations): array
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        $normalized = [];

        foreach ($relations as $key => $value) {
            if (is_int($key)) {
                $relation = trim((string) $value);
                $constraint = null;
            } else {
                $relation = trim((string) $key);
                $constraint = $value instanceof Closure ? $value : null;
            }

            if ($relation === '') {
                continue;
            }

            $normalized[$relation] = $constraint;
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed>|string $relations
     * @return array<string, string>
     */
    protected function normalizeCountRelations(array|string $relations): array
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        $normalized = [];

        foreach ($relations as $key => $value) {
            if (is_int($key)) {
                $expression = trim((string) $value);

                if ($expression === '') {
                    continue;
                }

                [$relation, $alias] = $this->parseCountExpression($expression);
            } else {
                $relation = trim((string) $key);

                if ($relation === '') {
                    continue;
                }

                $alias = is_string($value) && $value !== ''
                    ? trim($value)
                    : $this->guessCountAlias($relation);
            }

            if ($relation === '') {
                continue;
            }

            if ($alias === '') {
                $alias = $this->guessCountAlias($relation);
            }

            $normalized[$relation] = $alias;
        }

        return $normalized;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parseCountExpression(string $expression): array
    {
        $expression = trim($expression);

        if ($expression === '') {
            return ['', ''];
        }

        if (stripos($expression, ' as ') !== false) {
            [$name, $alias] = preg_split('/\s+as\s+/i', $expression, 2);
            $name = trim((string) ($name ?? ''));
            $alias = trim((string) ($alias ?? ''));

            if ($alias === '') {
                $alias = $this->guessCountAlias($name);
            }

            return [$name, $alias];
        }

        return [$expression, $this->guessCountAlias($expression)];
    }

    protected function guessCountAlias(string $relation): string
    {
        $relation = trim($relation);

        if ($relation === '') {
            return '';
        }

        return str_replace('.', '_', $relation) . '_count';
    }

    protected function addWhereHas(string $relation, ?Closure $callback, bool $not, string $boolean): self
    {
        [$name, $nested] = $this->parseRelationSegments($relation);

        $model = $this->newModelInstance();

        if (!method_exists($model, $name)) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] is not defined on model [%s].',
                $name,
                $this->modelClass
            ));
        }

        $relationInstance = $model->{$name}();

        if (! $relationInstance instanceof Relation) {
            throw new InvalidArgumentException(sprintf(
                'Method [%s] on model [%s] must return a relation instance.',
                $name,
                $this->modelClass
            ));
        }

        if ($nested !== null) {
            $nestedCallback = function (ModelQuery $query) use ($nested, $callback): void {
                $query->whereHas($nested, $callback);
            };
        } else {
            $nestedCallback = $callback;
        }

        $subQuery = $this->buildRelationExistenceQuery($relationInstance, $nestedCallback);

        if ($not) {
            $this->builder = $this->builder->whereNotExists($subQuery, $boolean);
        } else {
            $this->builder = $this->builder->whereExists($subQuery, $boolean);
        }

        return $this;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    protected function parseRelationSegments(string $relation): array
    {
        $relation = trim($relation);

        if ($relation === '') {
            throw new InvalidArgumentException('Relation name cannot be empty.');
        }

        if (!str_contains($relation, '.')) {
            return [$relation, null];
        }

        $segments = explode('.', $relation, 2);

        return [$segments[0], $segments[1] ?? null];
    }

    protected function buildRelationExistenceQuery(Relation $relation, ?Closure $callback): DBML
    {
        $relatedQuery = $relation->newExistenceQuery();

        if ($callback !== null) {
            $callback($relatedQuery);
        }

        $builder = $relatedQuery->toBase();
        $builder->select(DBML::raw('1'));

        if ($relation instanceof HasMany || $relation instanceof HasOne) {
            $parentColumn = $this->qualifyColumn($relation->getLocalKeyName());
            $relatedColumn = $this->qualifyRelationColumn($relation->getRelated(), $relation->getForeignKeyName());
            $builder->whereRaw(sprintf('%s = %s', $relatedColumn, $parentColumn));

            return $builder;
        }

        if ($relation instanceof BelongsTo) {
            $parentColumn = $this->qualifyColumn($relation->getForeignKeyName());
            $relatedColumn = $this->qualifyRelationColumn($relation->getRelated(), $relation->getOwnerKeyName());
            $builder->whereRaw(sprintf('%s = %s', $relatedColumn, $parentColumn));

            return $builder;
        }

        if ($relation instanceof BelongsToMany) {
            $parentColumn = $this->qualifyColumn($relation->getParentKeyName());
            $pivotForeign = $relation->getPivotTable() . '.' . $relation->getForeignPivotKeyName();
            $builder->whereRaw(sprintf('%s = %s', $pivotForeign, $parentColumn));

            return $builder;
        }

        throw new InvalidArgumentException(sprintf(
            'Relation type [%s] is not supported for whereHas queries.',
            $relation::class
        ));
    }

    protected function qualifyColumn(string $column): string
    {
        $column = trim($column);

        if ($column === '') {
            throw new InvalidArgumentException('Column name cannot be empty.');
        }

        if (str_contains($column, '.')) {
            return $column;
        }

        $table = $this->newModelInstance()->getTable();

        return $table . '.' . $column;
    }

    protected function qualifyRelationColumn(BaseModel $model, string $column): string
    {
        $column = trim($column);

        if ($column === '') {
            throw new InvalidArgumentException('Column name cannot be empty.');
        }

        if (str_contains($column, '.')) {
            return $column;
        }

        return $model->getTable() . '.' . $column;
    }

    /**
     * @param BaseModel[] $models
     * @return BaseModel[]
     */
    protected function hydrateModels(array $models): array
    {
        if ($models === []) {
            return $models;
        }

        $this->loadRelations($models);
        $this->appendRelationCounts($models);

        return $models;
    }

    protected function hydrateModel(?BaseModel $model): ?BaseModel
    {
        if ($model === null) {
            return null;
        }

        $this->loadRelations([$model]);
        $this->appendRelationCounts([$model]);

        return $model;
    }

    /**
     * @param BaseModel[] $models
     */
    protected function loadRelations(array $models): void
    {
        if ($this->eagerLoads === []) {
            return;
        }

        foreach ($this->eagerLoads as $relation => $constraint) {
            foreach ($models as $model) {
                if (! $model instanceof BaseModel) {
                    continue;
                }

                $this->loadRelation($model, $relation, $constraint);
            }
        }
    }

    /**
     * @param BaseModel[] $models
     */
    protected function appendRelationCounts(array $models): void
    {
        if ($this->relationCounts === []) {
            return;
        }

        foreach ($this->relationCounts as $relation => $alias) {
            $alias = $alias === '' ? $this->guessCountAlias($relation) : $alias;

            foreach ($models as $model) {
                if (! $model instanceof BaseModel) {
                    continue;
                }

                $count = $this->resolveRelationCount($model, $relation);
                $model->{$alias} = $count;
            }
        }
    }

    protected function loadRelation(BaseModel $model, string $relation, ?Closure $constraint = null): void
    {
        $relation = trim($relation);

        if ($relation === '') {
            return;
        }

        $segments = explode('.', $relation);
        $name = array_shift($segments);

        if ($name === null || $name === '') {
            return;
        }

        $results = null;

        if ($model->relationLoaded($name)) {
            $results = $model->getRelation($name);
        } elseif (method_exists($model, $name)) {
            $relationInstance = $model->{$name}();

            if ($relationInstance instanceof Relation) {
                if ($constraint instanceof Closure) {
                    $constraint($relationInstance->getQuery());
                }

                $results = $relationInstance->getResults();
            } else {
                $results = $relationInstance;
            }

            $model->setRelation($name, $results);
        }

        if ($segments === []) {
            return;
        }

        if ($results instanceof BaseModel) {
            $this->loadRelation($results, implode('.', $segments), null);

            return;
        }

        if (is_iterable($results)) {
            $next = implode('.', $segments);

            foreach ($results as $related) {
                if ($related instanceof BaseModel) {
                    $this->loadRelation($related, $next, null);
                }
            }
        }
    }

    protected function resolveRelationCount(BaseModel $model, string $relation): int
    {
        $relation = trim($relation);

        if ($relation === '') {
            return 0;
        }

        $name = explode('.', $relation)[0];

        if ($name === '') {
            return 0;
        }

        if (! $model->relationLoaded($name)) {
            $this->loadRelation($model, $name, null);
        }

        $value = $model->getRelation($name);

        if ($value instanceof BaseModel) {
            return 1;
        }

        if ($value === null) {
            return 0;
        }

        if (is_array($value)) {
            return count($value);
        }

        if ($value instanceof \Countable) {
            return count($value);
        }

        if ($value instanceof \Traversable) {
            return iterator_count($value);
        }

        return (int) $value;
    }

    /**
     * Clone the underlying builder and apply soft delete constraints.
     */
    protected function preparedBuilder(): DBML
    {
        $builder = clone $this->builder;

        if (! $this->usesSoftDeletes) {
            return $builder;
        }

        if ($this->onlyTrashed) {
            return $builder->whereNotNull($this->deletedAtColumn);
        }

        if ($this->includeTrashed) {
            return $builder;
        }

        return $builder->whereNull($this->deletedAtColumn);
    }
}
