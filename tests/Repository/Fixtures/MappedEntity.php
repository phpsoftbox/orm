<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Repository\Fixtures;

use DateTimeImmutable;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'mapped')]
final class MappedEntity
{
    #[Column(type: 'uuid')]
    public UuidInterface $id;

    #[Column(type: 'datetime')]
    public DateTimeImmutable $created;
}
