<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Options;

final readonly class PgArrayCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param 'string'|'int'|'float'|'bool'|'uuid'|'datetime'|null $itemType
     */
    public function __construct(
        public ?string $itemType = null,
        public bool $emptyStringAsEmptyArray = true,
    ) {
    }

    public function toArray(): array
    {
        return [
            'item_type' => $this->itemType,
            'empty_string_as_empty_array' => $this->emptyStringAsEmptyArray,
        ];
    }
}

