<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'uuid_entities')]
final class UuidEntity implements EntityInterface
{
    #[Id]
    #[GeneratedValue(strategy: 'uuid')]
    #[Column(type: 'uuid')]
    public readonly UuidInterface $id;

    public function __construct(
        #[Column(type: 'string')]
        public string $name,
    ) {
    }

    public function id(): ?UuidInterface
    {
        return $this->id ?? null;
    }
}
