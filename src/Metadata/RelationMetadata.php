<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata;

/**
 * Метаданные связи ManyToOne.
 */
final readonly class RelationMetadata
{
    /**
     * @param class-string $targetEntity
     * @param array<string, class-string> $morphMap
     * @param class-string|null $pivotEntity
     */
    public function __construct(
        public string $property,
        public string $type,
        public string $targetEntity,

        // belongsTo (many_to_one)
        public ?string $joinColumn = null,
        public string $referencedColumn = 'id',

        // hasOne/hasMany
        public ?string $foreignKey = null,
        public string $localKey = 'id',

        // belongsToMany
        public ?string $pivotTable = null,
        public ?string $foreignPivotKey = null,
        public ?string $relatedPivotKey = null,
        public string $parentKey = 'id',
        public string $relatedKey = 'id',
        public ?string $pivotEntity = null,
        public string $pivotAccessor = 'pivot',

        // hasManyThrough
        public ?string $throughEntity = null,
        public ?string $firstKey = null,
        public ?string $secondKey = null,
        public string $targetKey = 'id',

        // morphTo/morphMany
        public ?string $morphTypeColumn = null,
        public ?string $morphIdColumn = null,
        /** @var array<string, class-string> */
        public array $morphMap = [],
        public ?string $morphTypeValue = null,
    ) {
    }
}
