<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsTo;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'posts_rel')]
final class PostBelongsTo implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(name: 'author_id', type: 'int')]
        public int $authorId,
        #[BelongsTo(targetEntity: Author::class, joinColumn: 'authorId')]
        public ?Author $author = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
