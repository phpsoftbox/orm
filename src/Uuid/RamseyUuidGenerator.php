<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Uuid;

use PhpSoftBox\Orm\Contracts\UuidGeneratorInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class RamseyUuidGenerator implements UuidGeneratorInterface
{
    public function generate(): UuidInterface
    {
        // По умолчанию используем UUIDv7.
        return Uuid::uuid7();
    }
}
