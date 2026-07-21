<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsToMany;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\PivotScope;
use PhpSoftBox\Orm\Metadata\Attributes\RelationScope;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Scope\ActiveUserRoleScope;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Scope\AdminRolesScope;

#[Entity(table: 'users_roles_rel')]
final class UserWithScopedRoles implements EntityInterface
{
    /**
     * @param EntityCollection<Role> $roles
     */
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $name,

        /**
         * @var EntityCollection<Role>
         */
        #[BelongsToMany(
            targetEntity: Role::class,
            pivotTable: 'user_role_rel',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'role_id',
            parentKey: 'id',
            relatedKey: 'id',
        )]
        #[RelationScope(AdminRolesScope::class)]
        #[PivotScope(ActiveUserRoleScope::class)]
        public EntityCollection $roles,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
