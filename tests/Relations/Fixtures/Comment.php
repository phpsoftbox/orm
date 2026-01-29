<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'comments')]
final class Comment implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,

        #[Column(name: 'post_id', type: 'int')]
        public int $postId,

        #[Column(type: 'string')]
        public string $body,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}

