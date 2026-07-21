<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class HasManyThrough
{
    /**
     * @param class-string $targetEntity
     * @param class-string $throughEntity
     */
    public function __construct(
        public string $targetEntity,
        public string $throughEntity,
        public ?string $firstKey = null,
        public ?string $secondKey = null,
        public string $localKey = 'id',
        public string $targetKey = 'id',
    ) {
    }
}
