<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;

final class SimpleEntity implements EntityInterface
{
    public function __construct(
        private readonly int $id,
        public string $name,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}
