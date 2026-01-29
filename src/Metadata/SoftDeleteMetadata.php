<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata;

final readonly class SoftDeleteMetadata
{
    public function __construct(
        public string $entityField,
        public string $column,
    ) {
    }
}

