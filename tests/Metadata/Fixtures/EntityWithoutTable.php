<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity]
final class EntityWithoutTable implements EntityInterface
{
    #[Id]
    public int $id;

    public function id(): int|\Ramsey\Uuid\UuidInterface|null
    {
        return $this->id;
    }
}
