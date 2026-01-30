<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasMany;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'posts_nested_comments')]
final class PostWithNestedComments implements EntityInterface
{
    /**
     * @param EntityCollection<CommentWithAuthor>|null $comments
     */
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $title,
        #[HasMany(targetEntity: CommentWithAuthor::class, foreignKey: 'post_id', localKey: 'id')]
        public ?EntityCollection $comments = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
