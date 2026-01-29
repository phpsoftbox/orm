<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use InvalidArgumentException;

use function is_int;
use function is_numeric;

final class IntOrmHandler extends AbstractOrmTypeHandler
{
    public function supports(string $type): bool
    {
        return $type === 'int' || $type === 'integer';
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException('Cannot cast value to int');
    }
}
