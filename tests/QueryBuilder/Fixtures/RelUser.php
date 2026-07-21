<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsToMany;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'rel_users')]
final class RelUser implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $name,
    ) {
    }

    /**
     * @var EntityCollection<RelRole>|null
     */
    #[BelongsToMany(
        targetEntity: RelRole::class,
        pivotTable: 'rel_user_roles',
        foreignPivotKey: 'user_id',
        relatedPivotKey: 'role_id',
    )]
    public ?EntityCollection $roles = null;

    public function id(): int
    {
        return $this->id;
    }
}
