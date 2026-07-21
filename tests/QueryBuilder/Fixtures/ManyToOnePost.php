<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsTo;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'qb_posts')]
final class ManyToOnePost implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(name: 'author_id', type: 'int')]
        public int $authorId,
        #[Column(type: 'string')]
        public string $title,
    ) {
    }

    #[BelongsTo(targetEntity: ManyToOneAuthor::class, joinColumn: 'authorId')]
    public ?ManyToOneAuthor $author = null;

    public function id(): int
    {
        return $this->id;
    }
}
