<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Identifier;

use PhpSoftBox\Orm\Contracts\IdentifierInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Single-column идентификатор (id).
 */
final readonly class SingleIdentifier implements IdentifierInterface
{
    public function __construct(
        private string $column,
        private int|string|UuidInterface|null $value,
    ) {
    }

    public function values(): array
    {
        return [$this->column => $this->value];
    }
}

