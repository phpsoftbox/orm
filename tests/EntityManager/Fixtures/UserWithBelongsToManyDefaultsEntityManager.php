<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsToMany;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'users', connection: 'main')]
final class UserWithBelongsToManyDefaultsEntityManager implements EntityInterface
{
    #[Id]
    #[Column(type: 'primary')]
    public int $id;

    #[BelongsToMany(targetEntity: RoleWithBelongsToManyDefaultsEntityManager::class)]
    public array $roles = [];

    public function id(): int
    {
        return $this->id;
    }
}
