<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\FixturesPivot;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'roles_pivot_rel')]
final class Role implements EntityInterface
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[Column(type: 'string')]
    public string $name;

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}
