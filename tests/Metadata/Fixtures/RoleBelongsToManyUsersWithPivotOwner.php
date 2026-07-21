<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsToMany;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'roles')]
final class RoleBelongsToManyUsersWithPivotOwner implements EntityInterface
{
    #[Id]
    public int $id;

    /**
     * Связь объявлена в Role, но pivotOwner задан как User.
     * Ожидаем, что pivotTable будет сгенерирован как user_roles (owner-first).
     */
    #[BelongsToMany(targetEntity: UserWithBelongsToManyDefaults::class, pivotOwner: UserWithBelongsToManyDefaults::class)]
    public array $users = [];

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}
