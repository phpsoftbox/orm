<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\EventListener;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'listened_posts')]
#[EventListener(listener: RecordingEntityListener::class)]
final class ListenedPost implements EntityInterface
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'auto')]
        #[Column(type: 'int')]
        public ?int $id = null,
        #[Column(type: 'string')]
        public string $name = '',
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
