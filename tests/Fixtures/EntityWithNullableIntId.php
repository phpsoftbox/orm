<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;

final class EntityWithNullableIntId implements EntityInterface
{
    public function __construct(
        private int|null $id,
    ) {
    }

    public function id(): int|null
    {
        return $this->id;
    }
}
