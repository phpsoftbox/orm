<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\ChangeLog;

final readonly class EntityChangeContext
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int|string|null $initiatorId = null,
        public ?string $initiatorType = null,
        public array $metadata = [],
    ) {
    }
}
