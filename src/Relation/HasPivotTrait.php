<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Relation;

use PhpSoftBox\Orm\Contracts\EntityInterface;

/**
 * Базовая реализация HasPivotInterface.
 *
 * Подходит для MVP pivot-entity: вы добавляете trait в target entity (например Role)
 * и получаете методы setPivot()/pivot() без дублирования кода.
 *
 * @template TPivot of EntityInterface
 * @implements HasPivotInterface<TPivot>
 */
trait HasPivotTrait
{
    private ?EntityInterface $pivot = null;

    public function setPivot(EntityInterface $pivot): void
    {
        $this->pivot = $pivot;
    }

    public function pivot(): ?EntityInterface
    {
        return $this->pivot;
    }
}
