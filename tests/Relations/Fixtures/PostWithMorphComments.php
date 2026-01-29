<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\MorphMany;

#[Entity(table: 'posts_morph')]
final class PostWithMorphComments implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,

        #[Column(type: 'string')]
        public string $title,

        #[MorphMany(
            targetEntity: MorphComment::class,
            typeColumn: 'commentable_type',
            idColumn: 'commentable_id',
            typeValue: 'post',
            localKey: 'id',
        )]
        public EntityCollection $comments = new EntityCollection([]),
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
