<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\ManyToOne;

#[Entity(table: 'comments_nested')]
final class CommentWithAuthor implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,

        #[Column(name: 'post_id', type: 'int')]
        public int $postId,

        #[Column(name: 'author_id', type: 'int')]
        public int $authorId,

        #[Column(type: 'string')]
        public string $body,

        #[ManyToOne(targetEntity: AuthorNested::class, joinColumn: 'authorId', referencedColumn: 'id')]
        public ?AuthorNested $author = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
