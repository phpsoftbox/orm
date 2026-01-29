<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsToMany;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'users')]
final class UserWithBelongsToManyDefaults implements EntityInterface
{
    #[Id]
    public int $id;

    /**
     * Роли пользователя.
     *
     * pivotTable/foreignPivotKey/relatedPivotKey будут вычислены по конвенции:
     * - pivotTable: user_roles (ownerSingular + relatedPlural)
     * - foreignPivotKey: user_id
     * - relatedPivotKey: role_id
     */
    #[BelongsToMany(targetEntity: Role::class)]
    public array $roles = [];

    public function id(): int|\Ramsey\Uuid\UuidInterface|null
    {
        return $this->id;
    }
}
