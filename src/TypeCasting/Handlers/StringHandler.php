<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\Orm\TypeCasting\Contracts\TypeHandlerInterface;

use function is_scalar;
use function is_string;

final class StringHandler implements TypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'string';
    }

    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException('Cannot cast value to string');
    }
}
