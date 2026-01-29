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
}
