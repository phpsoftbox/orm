<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\Sluggable;

#[Entity(table: 'posts_update')]
#[Sluggable(source: 'title', target: 'slug', onUpdate: true)]
final class PostWithSlugOnUpdate implements EntityInterface
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
