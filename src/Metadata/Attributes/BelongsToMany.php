<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class BelongsToMany
{
    /**
     * @param class-string $targetEntity
     * @param class-string|null $pivotOwner
     */
    public function __construct(
        public string $targetEntity,
        public ?string $pivotTable = null,
        public ?string $foreignPivotKey = null,
        public ?string $relatedPivotKey = null,
        public ?string $pivotOwner = null,
        public string $parentKey = 'id',
        public string $relatedKey = 'id',
    ) {
    }
}
