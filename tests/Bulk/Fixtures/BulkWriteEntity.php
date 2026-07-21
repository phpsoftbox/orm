<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Bulk\Fixtures;

use DateTimeImmutable;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\SoftDelete;

#[Entity(table: 'bulk_write_entities')]
#[SoftDelete(entityField: 'deletedDatetime', column: 'deleted_datetime')]
final class BulkWriteEntity implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $name,
        #[Column(type: 'datetime', nullable: true, name: 'deleted_datetime')]
        public ?DateTimeImmutable $deletedDatetime = null,
        #[Column(type: 'datetime', nullable: true, name: 'updated_datetime')]
        public ?DateTimeImmutable $updatedDatetime = null,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}
