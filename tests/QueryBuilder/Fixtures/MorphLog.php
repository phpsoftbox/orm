<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\MorphTo;

#[Entity(table: 'morph_logs')]
final class MorphLog implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $subjectType,
        #[Column(type: 'int')]
        public int $subjectId,
    ) {
    }

    #[MorphTo(
        typeColumn: 'subjectType',
        idColumn: 'subjectId',
        map: [
            'post'  => MorphPost::class,
            'video' => MorphVideo::class,
        ],
    )]
    public mixed $subject = null;

    public function id(): int
    {
        return $this->id;
    }
}
