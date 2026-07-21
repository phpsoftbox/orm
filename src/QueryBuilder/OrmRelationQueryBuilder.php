<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\QueryBuilder;

use BackedEnum;
use InvalidArgumentException;
use PhpSoftBox\Database\QueryBuilder\SelectQueryBuilder;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;

use function in_array;
use function str_contains;
use function strtoupper;

/**
 * Вспомогательный билдер для whereHas().
 */
final class OrmRelationQueryBuilder
{
    private int $parameterCounter = 0;

    public function __construct(
        private readonly SelectQueryBuilder|OrmSelectQueryBuilder $parent,
        private readonly string $relatedAlias,
        private readonly ?string $pivotAlias = null,
        private readonly ?EntityManagerInterface $entityManager = null,
        private readonly ?string $entityClass = null,
    ) {
    }

    public function alias(): string
    {
        return $this->relatedAlias;
    }

    public function pivotAlias(): ?string
    {
        return $this->pivotAlias;
    }

    /**
     * Возвращает квалифицированное имя колонки, если нет явного алиаса.
     */
    public function qualify(string $column, bool $usePivot = false): string
    {
        if ($column === '' || str_contains($column, '.')) {
            return $column;
        }

        $alias = $usePivot ? ($this->pivotAlias ?? '') : $this->relatedAlias;
        if ($alias === '') {
            return $column;
        }

        return $alias . '.' . $column;
    }

    /**
     * Добавляет условие WHERE в запрос владельца.
     *
     * @param array<string|int, mixed> $params
     */
    public function where(string $condition, array $params = []): self
    {
        $this->parent->where($condition, $params);

        return $this;
    }

    /**
     * Добавляет условие OR WHERE в запрос владельца.
     *
     * @param array<string|int, mixed> $params
     */
    public function orWhere(string $condition, array $params = []): self
    {
        $this->parent->orWhere($condition, $params);

        return $this;
    }

    /**
     * Добавляет условие WHERE IN в запрос владельца.
     *
     * @param array<int, mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        $this->parent->whereIn($this->qualify($column), $values);

        return $this;
    }

    /**
     * Добавляет условие WHERE NOT IN в запрос владельца.
     *
     * @param array<int, mixed> $values
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->parent->whereNotIn($this->qualify($column), $values);

        return $this;
    }

    /**
     * Добавляет условие по property или колонке terminal entity.
     */
    public function whereProperty(string $propertyOrColumn, string $operator, mixed $value): self
    {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE'], true)) {
            throw new InvalidArgumentException('Unsupported relation where operator: ' . $operator);
        }

        $column = $propertyOrColumn;
        if ($this->entityManager !== null && $this->entityClass !== null) {
            $metadata = $this->entityManager->metadataProvider()->for($this->entityClass);
            $column   = $metadata->columns[$propertyOrColumn]->column ?? $propertyOrColumn;
        }

        $qualified = $this->qualify($column);
        if ($value === null) {
            $condition = in_array($operator, ['!=', '<>'], true) ? ' IS NOT NULL' : ' IS NULL';
            $this->parent->where($qualified . $condition);

            return $this;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        $parameter = '__orm_' . $this->relatedAlias . '_where_' . (++$this->parameterCounter);
        $this->parent->where($qualified . ' ' . $operator . ' :' . $parameter, [$parameter => $value]);

        return $this;
    }
}
