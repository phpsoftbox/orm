<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\Sluggable;

#[Entity(table: 'posts_mapped')]
#[Sluggable(source: 'title', target: 'seoSlug', prefix: '{id}-')]
final class PostWithMappedSlug implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(name: 'headline', type: 'string')]
        public string $title,
        #[Column(name: 'seo_slug', type: 'string')]
        public string $seoSlug,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
