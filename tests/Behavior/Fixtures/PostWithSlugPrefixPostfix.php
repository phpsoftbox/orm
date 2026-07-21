<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\Sluggable;

#[Entity(table: 'posts2')]
#[Sluggable(source: 'title', target: 'slug', prefix: '{id}-', postfix: '.html')]
final class PostWithSlugPrefixPostfix implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $title,
        #[Column(type: 'string')]
        public string $slug,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
