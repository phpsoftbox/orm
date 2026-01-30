<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\Orm\TypeCasting\Contracts\TypeHandlerInterface;

use function is_int;
use function is_numeric;

final class IntHandler implements TypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'int' || $type === 'integer';
    }

    public function cast(mixed $value): mixed
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
