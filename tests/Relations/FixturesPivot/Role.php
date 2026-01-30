<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\FixturesPivot;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Relation\HasPivotInterface;
use PhpSoftBox\Orm\Relation\HasPivotTrait;

/**
 * @implements HasPivotInterface<UserRole>
 */
#[Entity(table: 'roles_pivot_rel')]
final class Role implements EntityInterface, HasPivotInterface
{
    /**
     * @use HasPivotTrait<UserRole>
     */
    use HasPivotTrait;

    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[Column(type: 'string')]
    public string $name;

    public function id(): int|\Ramsey\Uuid\UuidInterface|null
    {
        return $this->id;
    }
}
