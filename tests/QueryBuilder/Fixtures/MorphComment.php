<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'morph_comments')]
final class MorphComment implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $commentableType,
        #[Column(type: 'int')]
        public int $commentableId,
        #[Column(type: 'string')]
        public string $body,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}
