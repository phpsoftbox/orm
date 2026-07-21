<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use Ramsey\Uuid\UuidInterface;

interface UuidGeneratorInterface
{
    /**
     * Генерирует UUID для сущностей.
     */
    public function generate(): UuidInterface;
}
