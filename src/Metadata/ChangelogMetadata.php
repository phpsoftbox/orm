<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata;

use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;

final readonly class ChangelogMetadata
{
    /**
     * @param class-string<EntityChangeLoggerInterface>|null $logHandler
     * @param list<string> $ignore
     */
    public function __construct(
        public bool $enabled = true,
        public ?string $logHandler = null,
        public array $ignore = [],
    ) {
    }
}
