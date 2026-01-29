<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Relation;

use PhpSoftBox\Orm\Contracts\EntityInterface;

/**
 * Контракт для сущностей, которые могут хранить pivot-данные (для BelongsToMany).
 *
 * Важно: это не магическое свойство, а явный API, чтобы IDE могла подсказать тип.
 *
 * @template TPivot of EntityInterface
 */
interface HasPivotInterface
{
    /**
     * Устанавливает pivot-сущность (строку pivot-таблицы).
     *
     * @param TPivot $pivot
     */
    public function setPivot(EntityInterface $pivot): void;

    /**
     * Возвращает pivot-сущность.
     *
     * @return TPivot|null
     */
    public function pivot(): ?EntityInterface;
}
