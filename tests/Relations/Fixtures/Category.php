<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasMany;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\ManyToOne;

#[Entity(table: 'categories')]
final class Category implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,

        #[Column(name: 'parent_id', type: 'int', nullable: true)]
        public ?int $parentId = null,

        #[ManyToOne(targetEntity: self::class, joinColumn: 'parentId')]
        public ?self $parent = null,

        #[HasMany(targetEntity: self::class, foreignKey: 'parent_id', localKey: 'id')]
        public EntityCollection $children = new EntityCollection([]),
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
