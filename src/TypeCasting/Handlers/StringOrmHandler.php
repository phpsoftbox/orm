<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

final class StringOrmHandler extends AbstractOrmTypeHandler
{
    public function supports(string $type): bool
    {
        return $type === 'string';
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
