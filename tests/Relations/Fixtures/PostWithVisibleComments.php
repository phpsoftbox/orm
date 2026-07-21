<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasMany;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\RelationScope;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Scope\VisibleCommentsScope;

#[Entity(table: 'posts_comments')]
final class PostWithVisibleComments implements EntityInterface
{
    /**
     * @param EntityCollection<Comment>|null $visibleComments
     */
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $title,
        #[HasMany(targetEntity: Comment::class, foreignKey: 'post_id', localKey: 'id')]
        #[RelationScope(VisibleCommentsScope::class)]
        public ?EntityCollection $visibleComments = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
