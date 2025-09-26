<?php

declare(strict_types=1);

namespace Zero\Lib\Model;

/**
 * Base relationship type responsible for retrieving related models.
 */
abstract class Relation
{
    public function __construct(
        protected ModelQuery $query,
        protected ModelQuery $baseQuery,
        protected \Zero\Lib\Model $parent,
        protected \Zero\Lib\Model $related
    ) {
        $this->baseQuery = clone $baseQuery;
    }

    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->{$method}(...$parameters);

        if ($result instanceof ModelQuery) {
            $this->query = $result;

            return $this;
        }

        return $result;
    }

    public function getQuery(): ModelQuery
    {
        return $this->query;
    }

    public function newExistenceQuery(): ModelQuery
    {
        return clone $this->baseQuery;
    }

    public function getParent(): \Zero\Lib\Model
    {
        return $this->parent;
    }

    public function getRelated(): \Zero\Lib\Model
    {
        return $this->related;
    }

    /**
     * Retrieve the relationship results.
     */
    abstract public function getResults(): mixed;
}
