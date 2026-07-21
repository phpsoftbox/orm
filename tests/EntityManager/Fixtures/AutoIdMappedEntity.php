<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'users')]
final class AutoIdMappedEntity implements EntityInterface
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'auto')]
        #[Column(type: 'int')]
        public ?int $id,
        #[Column(type: 'string')]
        public string $name,
        #[Column(name: 'deleted_datetime', type: 'string', nullable: true)]
        public ?string $deletedDatetime = null,
    ) {
    }

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}
