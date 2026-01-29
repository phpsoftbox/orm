<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Contracts;

interface OrmTypeCasterInterface extends TypeCasterInterface
{
    public function castTo(string $type, mixed $value, array $options = []): int|float|string|bool|null;

    public function castFrom(string $type, mixed $value, array $options = []): mixed;
}
