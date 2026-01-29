<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsTo;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'posts')]
final class EntityWithBelongsToConvention implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,

        #[Column(name: 'author_id', type: 'int')]
        public int $authorId,

        // joinColumn не задан: должен быть выведен как authorId (propertyName + 'Id')
        #[BelongsTo(targetEntity: UserEntity::class)]
        public ?UserEntity $author = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
