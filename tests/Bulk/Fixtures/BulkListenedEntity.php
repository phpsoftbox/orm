<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Bulk\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\EventListener;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'bulk_cast_entities')]
#[EventListener(listener: BulkRecordingListener::class)]
final class BulkListenedEntity implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $name,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}
