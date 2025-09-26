<?php

declare(strict_types=1);

namespace Zero\Lib\Model\Relations;

use RuntimeException;
use Zero\Lib\DB\DBML;
use Zero\Lib\Model as BaseModel;
use Zero\Lib\Model\ModelQuery;
use Zero\Lib\Model\Relation;

/**
 * Represents a belongs-to-many relationship.
 */
class BelongsToMany extends Relation
{
    public function __construct(
        ModelQuery $query,
        ModelQuery $baseQuery,
        BaseModel $parent,
        BaseModel $related,
        protected string $pivotTable,
        protected string $foreignPivotKey,
        protected string $relatedPivotKey,
        protected string $parentKey,
        protected string $relatedKey,
        protected mixed $parentKeyValue,
        protected ?string $relationName = null
    ) {
        parent::__construct($query, $baseQuery, $parent, $related);
    }

    protected bool $withTimestamps = false;
    protected string $pivotCreatedAt = 'created_at';
    protected string $pivotUpdatedAt = 'updated_at';

    public function getResults(): array
    {
        if ($this->parentKeyValue === null) {
            return [];
        }

        return $this->query->get();
    }

    public function getPivotTable(): string
    {
        return $this->pivotTable;
    }

    public function getForeignPivotKeyName(): string
    {
        return $this->foreignPivotKey;
    }

    public function getRelatedPivotKeyName(): string
    {
        return $this->relatedPivotKey;
    }

    public function getParentKeyName(): string
    {
        return $this->parentKey;
    }

    public function getRelatedKeyName(): string
    {
        return $this->relatedKey;
    }

    /**
     * Maintain created_at / updated_at columns on the pivot table.
     */
    public function withTimestamps(string $createdAt = 'created_at', string $updatedAt = 'updated_at'): self
    {
        $this->withTimestamps = true;
        $this->pivotCreatedAt = $createdAt;
        $this->pivotUpdatedAt = $updatedAt;

        return $this;
    }

    /**
     * Attach related models to the parent via the pivot table.
     */
    public function attach(int|string|array $ids, array $attributes = []): void
    {
        if ($this->parentKeyValue === null) {
            throw new RuntimeException('Cannot attach records without a persisted parent model.');
        }

        $records = $this->buildPivotRecords($ids, $attributes);

        if ($records === []) {
            return;
        }

        DBML::table($this->pivotTable)->insert($records);
        $this->forgetCachedRelation();
    }

    /**
     * Detach related models from the parent.
     */
    public function detach(int|string|array|null $ids = null): int
    {
        if ($this->parentKeyValue === null) {
            return 0;
        }

        $query = DBML::table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parentKeyValue);

        if ($ids !== null) {
            $ids = $this->normalizeIds($ids);

            if ($ids === []) {
                return 0;
            }

            $query = $query->whereIn($this->relatedPivotKey, $ids);
        }

        $deleted = $query->delete();

        if ($deleted > 0) {
            $this->forgetCachedRelation();
        }

        return $deleted;
    }

    /**
     * Synchronise the relationship with the given IDs.
     */
    public function sync(array $ids, bool $detaching = true): void
    {
        if ($this->parentKeyValue === null) {
            throw new RuntimeException('Cannot sync records without a persisted parent model.');
        }

        $desiredRecords = $this->buildPivotRecords($ids, []);
        $desired = [];

        foreach ($desiredRecords as $record) {
            $desired[(string) $record[$this->relatedPivotKey]] = $record;
        }

        $existing = DBML::table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parentKeyValue)
            ->pluck($this->relatedPivotKey);

        $existing = array_map('strval', $existing);

        $toInsert = [];
        $toUpdate = [];
        foreach ($desired as $key => $record) {
            if (!in_array($key, $existing, true)) {
                $toInsert[] = $record;
            } else {
                $toUpdate[$key] = $record;
            }
        }

        $detached = [];
        if ($detaching) {
            foreach ($existing as $current) {
                if (!array_key_exists($current, $desired)) {
                    $detached[] = $current;
                }
            }
        }

        if ($detached !== []) {
            DBML::table($this->pivotTable)
                ->where($this->foreignPivotKey, $this->parentKeyValue)
                ->whereIn($this->relatedPivotKey, $detached)
                ->delete();
        }

        if ($toInsert !== []) {
            DBML::table($this->pivotTable)->insert($toInsert);
        }

        if ($toUpdate !== []) {
            foreach ($toUpdate as $key => $record) {
                $update = $this->preparePivotUpdate($record);

                if ($update === []) {
                    continue;
                }

                DBML::table($this->pivotTable)
                    ->where($this->foreignPivotKey, $this->parentKeyValue)
                    ->where($this->relatedPivotKey, $key)
                    ->update($update);
            }
        }

        if ($detached !== [] || $toInsert !== [] || $toUpdate !== []) {
            $this->forgetCachedRelation();
        }
    }

    protected function buildPivotRecords(int|string|array $ids, array $attributes): array
    {
        $normalized = $this->normalizeAttachData($ids, $attributes);
        $records = [];

        foreach ($normalized as $id => $extra) {
            $record = array_merge([
                $this->foreignPivotKey => $this->parentKeyValue,
                $this->relatedPivotKey => $id,
            ], $extra);

            $records[] = $this->applyPivotTimestamps($record);
        }

        return $records;
    }

    protected function normalizeAttachData(int|string|array $ids, array $attributes): array
    {
        if (!is_array($ids)) {
            return [$ids => $attributes];
        }

        $results = [];

        foreach ($ids as $key => $value) {
            if (is_array($value)) {
                $results[$key] = $value;
                continue;
            }

            if (is_int($key)) {
                $results[$value] = $attributes;
            } else {
                $results[$key] = $attributes;
            }
        }

        return $results;
    }

    protected function normalizeIds(int|string|array $ids): array
    {
        if (!is_array($ids)) {
            return [$ids];
        }

        $values = [];

        foreach ($ids as $key => $value) {
            $values[] = is_int($key) ? $value : $key;
        }

        return $values;
    }

    protected function forgetCachedRelation(): void
    {
        if ($this->relationName) {
            $this->parent->setRelation($this->relationName, null);
        }
    }

    protected function applyPivotTimestamps(array $attributes, bool $updating = false): array
    {
        if (! $this->withTimestamps) {
            return $attributes;
        }

        $now = date('Y-m-d H:i:s');

        if (! $updating && !array_key_exists($this->pivotCreatedAt, $attributes)) {
            $attributes[$this->pivotCreatedAt] = $now;
        }

        if ($updating) {
            $attributes[$this->pivotUpdatedAt] = $now;
        } elseif (!array_key_exists($this->pivotUpdatedAt, $attributes)) {
            $attributes[$this->pivotUpdatedAt] = $now;
        }

        return $attributes;
    }

    protected function preparePivotUpdate(array $record): array
    {
        $update = $record;

        unset($update[$this->foreignPivotKey], $update[$this->relatedPivotKey]);

        if ($this->withTimestamps) {
            unset($update[$this->pivotCreatedAt]);
        }

        $update = $this->applyPivotTimestamps($update, true);

        return $update;
    }

}
