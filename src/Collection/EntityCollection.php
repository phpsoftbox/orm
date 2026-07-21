<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Collection;

use PhpSoftBox\Collection\Collection;

/**
 * Коллекция сущностей.
 *
 * Отличие от базовой Collection:
 * - семантика (это именно набор сущностей),
 * - типизация через PHPDoc для IDE/стат. анализа.
 *
 * @template TEntity of object
 * @extends Collection<int, TEntity>
 */
final class EntityCollection extends Collection
{
    /**
     * @param list<TEntity> $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
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
