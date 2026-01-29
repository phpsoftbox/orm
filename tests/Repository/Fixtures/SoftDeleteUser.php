<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Repository\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\SoftDelete;

#[Entity(table: 'sd_users')]
#[SoftDelete(entityField: 'deletedDatetime', column: 'deleted_datetime')]
final class SoftDeleteUser implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $name,
        #[Column(type: 'datetime', nullable: true)]
        public ?string $deletedDatetime = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
