<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Changelog
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
