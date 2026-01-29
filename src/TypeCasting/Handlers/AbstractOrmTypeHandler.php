<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use PhpSoftBox\Orm\TypeCasting\Contracts\OrmTypeHandlerInterface;

/**
 * Базовый ORM handler с pass-through реализацией.
 *
 * Большинству типов достаточно переопределить только castFrom() или castTo().
 */
abstract class AbstractOrmTypeHandler implements OrmTypeHandlerInterface
{
    public function castTo(mixed $value, array $options = []): int|float|string|bool|null
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
            return $value;
        }

        // По умолчанию приводим к строке.
        return (string) $value;
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        return $value;
    }

    // По умолчанию универсальный cast() трактуем как "castFrom".
    public function cast(mixed $value): mixed
    {
        return $this->castFrom($value);
    }
}

