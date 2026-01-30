<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\FixturesPivot;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsToMany;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'users_pivot_rel')]
final class User implements EntityInterface
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[Column(type: 'string')]
    public string $name;

    /**
     * @var EntityCollection<Role>
     */
    #[BelongsToMany(
        targetEntity: Role::class,
        pivotTable: 'user_role_pivot_rel',
        foreignPivotKey: 'user_id',
        relatedPivotKey: 'role_id',
        pivotEntity: UserRole::class,
        pivotAccessor: 'pivot',
    )]
    public EntityCollection $roles;

    public function __construct()
    {
        $this->roles = new EntityCollection([]);
    }

    public function id(): int|\Ramsey\Uuid\UuidInterface|null
    {
        return $this->id;
    }
}
