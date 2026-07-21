<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class HasOne
{
    /**
     * @param class-string $targetEntity
     */
    public function __construct(
        public string $targetEntity,
        public ?string $foreignKey = null,
        public string $localKey = 'id',
    ) {
    }
}
