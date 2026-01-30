<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\MorphTo;

#[Entity(table: 'morph_comments')]
final class MorphComment implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(name: 'commentable_type', type: 'string')]
        public string $commentableType,
        #[Column(name: 'commentable_id', type: 'int')]
        public int $commentableId,
        #[MorphTo(
            typeColumn: 'commentable_type',
            idColumn: 'commentable_id',
            map: [
                'post'  => Post::class,
                'video' => Video::class,
            ],
        )]
        public object|null $commentable = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
