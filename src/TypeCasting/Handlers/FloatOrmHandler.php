<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use InvalidArgumentException;

final class FloatOrmHandler extends AbstractOrmTypeHandler
{
    public function supports(string $type): bool
    {
        return $type === 'float' || $type === 'double';
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        throw new InvalidArgumentException('Cannot cast value to float');
    }
}

