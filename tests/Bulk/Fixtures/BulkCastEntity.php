<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Bulk\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'bulk_cast_entities')]
final class BulkCastEntity implements EntityInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $name,
        #[Column(type: 'enum')]
        public BulkStatus $status,
        #[Column(name: 'is_active', type: 'bool')]
        public bool $active,
        #[Column(type: 'json')]
        public array $payload,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}
