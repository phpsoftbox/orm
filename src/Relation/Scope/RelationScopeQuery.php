<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Relation\Scope;

use PhpSoftBox\Database\QueryBuilder\SelectQueryBuilder;

use function str_contains;

final class RelationScopeQuery
{
    public function __construct(
        private readonly SelectQueryBuilder $query,
        private readonly ?string $alias = null,
    ) {
    }

    public function query(): SelectQueryBuilder
    {
        return $this->query;
    }

    public function alias(): ?string
    {
        return $this->alias;
    }

    public function qualify(string $column): string
    {
        if ($column === '' || str_contains($column, '.') || $this->alias === null || $this->alias === '') {
            return $column;
        }

        return $this->alias . '.' . $column;
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function where(string $condition, array $params = []): self
    {
        $this->query->where($condition, $params);

        return $this;
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function whereRaw(string $condition, array $params = []): self
    {
        $this->query->whereRaw($condition, $params);

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->query->whereNull($this->qualify($column));

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->query->whereNotNull($this->qualify($column));

        return $this;
    }

    /**
     * @param array<int, mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        $this->query->whereIn($this->qualify($column), $values);

        return $this;
    }

    /**
     * @param array<int, mixed> $values
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->query->whereNotIn($this->qualify($column), $values);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->query->orderBy($this->qualify($column), $direction);

        return $this;
    }
}
