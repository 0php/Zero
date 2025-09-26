<?php

declare(strict_types=1);

namespace Zero\Lib\Model\Relations;

use Zero\Lib\Model as BaseModel;
use Zero\Lib\Model\ModelQuery;
use Zero\Lib\Model\Relation;

/**
 * Represents a belongs-to relationship.
 */
class BelongsTo extends Relation
{
    public function __construct(
        ModelQuery $query,
        ModelQuery $baseQuery,
        BaseModel $parent,
        BaseModel $related,
        protected string $foreignKey,
        protected string $ownerKey,
        protected mixed $foreignValue,
        protected ?string $relationName = null
    ) {

        parent::__construct($query, $baseQuery, $parent, $related);
    }

    public function getResults(): ?BaseModel
    {
        if ($this->foreignValue === null) {
            return null;
        }

        $result = $this->query->first();

        return $result instanceof BaseModel ? $result : null;
    }

    /**
     * Associate the parent model with the given instance.
     */
    public function associate(BaseModel $model): BaseModel
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
    public function dissociate(): BaseModel
    {
        $this->parent->{$this->foreignKey} = null;
        if ($this->relationName) {
            $this->parent->setRelation($this->relationName, null);
        }

        return $this->parent;
    }

    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    public function getOwnerKeyName(): string
    {
        return $this->ownerKey;
    }
}
