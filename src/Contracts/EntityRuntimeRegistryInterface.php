<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use PhpSoftBox\Orm\UnitOfWork\EntityNode;

interface EntityRuntimeRegistryInterface
{
    public function node(EntityInterface $entity): ?EntityNode;

    public function register(EntityInterface $entity, EntityNode $node): void;

    public function remove(EntityInterface $entity, EntityNode $node): void;
}
