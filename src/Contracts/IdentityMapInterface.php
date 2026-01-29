<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use PhpSoftBox\Orm\Identity\EntityKey;

interface IdentityMapInterface
{
    public function get(EntityKey $key): ?EntityInterface;

    public function set(EntityKey $key, EntityInterface $entity): void;

    public function remove(EntityKey $key): void;

    public function clear(): void;
}

