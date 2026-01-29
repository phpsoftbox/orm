<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasOne;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'users_profile')]
final class UserWithProfile implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,

        #[Column(type: 'string')]
        public string $name,

        #[HasOne(targetEntity: Profile::class, foreignKey: 'user_id', localKey: 'id')]
        public ?Profile $profile = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}

