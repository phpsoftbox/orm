<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Collection;

use PhpSoftBox\Collection\Collection;

use function spl_object_id;

/**
 * Коллекция сущностей.
 *
 * Отличие от базовой Collection:
 * - семантика (это именно набор сущностей),
 * - типизация через PHPDoc для IDE/стат. анализа,
 * - для связи BelongsToMany с `pivotEntity` хранит pivot-сущность каждого элемента ({@see pivot()}).
 *
 * @template TEntity of object
 * @extends Collection<int, TEntity>
 */
final class EntityCollection extends Collection
{
    /**
     * @var array<int, object>
     */
    private array $pivots = [];

    /**
     * @param list<TEntity> $items
     * @param array<int, object> $pivots pivot-сущности, индексированные по spl_object_id() элемента
     */
    public function __construct(array $items = [], array $pivots = [])
    {
        parent::__construct($items);

        $this->pivots = $pivots;
    }

    /**
     * Возвращает pivot-сущность (строку pivot-таблицы) для элемента коллекции связи BelongsToMany.
     *
     * Pivot относится к паре owner + related, поэтому хранится в коллекции связи owner, а не в related-сущности.
     * Возвращает null, если элемент не из этой коллекции или у связи не задан `pivotEntity`.
     */
    public function pivot(object $entity): ?object
    {
        return $this->pivots[spl_object_id($entity)] ?? null;
    }

    /**
     * @param list<TEntity> $items
     */
    public static function from(array $items): self
    {
        return new self($items);
    }

    /**
     * @return list<TEntity>
     */
    public function all(): array
    {
        /** @var list<TEntity> $items */
        $items = parent::all();

        return $items;
    }

    /**
     * @param callable(TEntity): bool|null $fn
     * @return TEntity|null
     */
    public function first(?callable $fn = null, mixed $default = null): mixed
    {
        return parent::first($fn, $default);
    }

    /**
     * @param callable(TEntity): bool|null $fn
     * @return TEntity|null
     */
    public function last(?callable $fn = null, mixed $default = null): mixed
    {
        return parent::last($fn, $default);
    }
}
