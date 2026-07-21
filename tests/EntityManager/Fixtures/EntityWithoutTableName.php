<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(connection: 'main')]
final class EntityWithoutTableName implements EntityInterface
{
    #[Id]
    #[Column(type: 'primary')]
    public int $id;

    public function id(): int
    {
        return $this->id;
    }
}
