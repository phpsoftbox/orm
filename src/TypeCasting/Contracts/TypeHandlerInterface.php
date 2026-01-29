<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Contracts;

/**
 * Универсальный handler для общего TypeCaster (не ORM).
 *
 * Здесь нет направлений "БД->PHP" или "PHP->БД".
 */
interface TypeHandlerInterface
{
    /**
     * Может ли handler обработать данный тип.
     */
    public function supports(string $type): bool;

    /**
     * Приводит значение к указанному типу.
     */
    public function cast(mixed $value): mixed;
}
