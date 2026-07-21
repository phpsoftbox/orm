<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata;

use PhpSoftBox\DataCasting\Options\TypeCastingOptionsInterface;

final readonly class PropertyMetadata
{
    public function __construct(
        public string $property,
        public string $column,
        public string $type,
        public ?int $length,
        public bool $nullable,
        public mixed $default,
        public bool $isId,
        public bool $insertable,
        public bool $updatable,
        public ?TypeCastingOptionsInterface $options = null,
        public ?string $phpType = null,
        /** @var class-string|null */
        public ?string $jsonCollectionItemClass = null,
        /** @var class-string|null */
        public ?string $jsonMapValueClass = null,
        /** @var class-string|null */
        public ?string $jsonFactoryClass = null,
    ) {
    }
}
