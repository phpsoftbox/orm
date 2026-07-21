<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasOne;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'users_ho')]
final class EntityWithHasOneConvention implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[HasOne(targetEntity: UserEntity::class)]
        public ?UserEntity $profile = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
