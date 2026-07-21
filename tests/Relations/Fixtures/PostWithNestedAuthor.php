<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\ManyToOne;

#[Entity(table: 'posts_nested')]
final class PostWithNestedAuthor implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(name: 'author_id', type: 'int')]
        public int $authorId,
        #[ManyToOne(targetEntity: AuthorWithCompany::class, joinColumn: 'authorId')]
        public ?AuthorWithCompany $author = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
