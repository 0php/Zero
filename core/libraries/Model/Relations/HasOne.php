<?php

declare(strict_types=1);

namespace Zero\Lib\Model\Relations;

use Zero\Lib\Model as BaseModel;
use Zero\Lib\Model\ModelQuery;
use Zero\Lib\Model\Relation;

/**
 * Represents a has-one relationship.
 */
class HasOne extends Relation
{
    public function __construct(
        ModelQuery $query,
        ModelQuery $baseQuery,
        BaseModel $parent,
        BaseModel $related,
        protected string $foreignKey,
        protected string $localKey,
        protected mixed $localValue
    ) {
        parent::__construct($query, $baseQuery, $parent, $related);
    }

    public function getResults(): ?BaseModel
    {
        if ($this->localValue === null) {
            return null;
        }

        $result = $this->query->first();

        return $result instanceof BaseModel ? $result : null;
    }

    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }
}
