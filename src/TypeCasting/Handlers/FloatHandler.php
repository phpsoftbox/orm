<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use PhpSoftBox\Orm\TypeCasting\Contracts\TypeHandlerInterface;

final class FloatHandler implements TypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'float' || $type === 'double';
    }

    public function cast(mixed $value): mixed
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

        throw new \InvalidArgumentException('Cannot cast value to float');
    }
}
