<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;

use function is_scalar;
use function is_string;

final class MarkerTypeHandler implements TypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'marker';
    }

    public function castTo(mixed $value, array $options = []): int|float|string|bool|null
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $raw = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');

        return 'mapped:' . $raw;
    }
}
