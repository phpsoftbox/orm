<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'users')]
final class User implements EntityInterface
{
    public function __construct(
        public readonly UuidInterface $id,
        public string $name,
    ) {
    }

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}
