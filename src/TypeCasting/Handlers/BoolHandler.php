<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use PhpSoftBox\Orm\TypeCasting\Contracts\TypeHandlerInterface;

final class BoolHandler implements TypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'bool' || $type === 'boolean';
    }

    public function cast(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $v = strtolower(trim($value));
            return match ($v) {
                '1', 'true', 'yes', 'y', 'on' => true,
                '0', 'false', 'no', 'n', 'off' => false,
                default => (bool) $value,
            };
        }

        return (bool) $value;
    }
}
