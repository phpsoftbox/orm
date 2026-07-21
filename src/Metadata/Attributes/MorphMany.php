<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class MorphMany
{
    /**
     * @param class-string $targetEntity
     */
    public function __construct(
        public string $targetEntity,
        public string $typeColumn,
        public string $idColumn,
        public string $typeValue,
        public string $localKey = 'id',
    ) {
    }
}
