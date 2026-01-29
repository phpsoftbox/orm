<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class MorphTo
{
    /**
     * @param array<string, class-string> $map
     */
    public function __construct(
        public string $typeColumn,
        public string $idColumn,
        public array $map,
    ) {
    }
}
