<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;
use PhpSoftBox\Orm\TypeCasting\Options\TypeCastingOptionsInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    public function __construct(
        public ?string $name = null,
        public string $type = 'string',
        public ?int $length = null,
        public bool $nullable = false,
        public mixed $default = null,
        public bool $insertable = true,
        public bool $updatable = true,
        /**
         * Типизированные опции кастинга для данного поля.
         */
        public ?TypeCastingOptionsInterface $options = null,
    ) {
    }
}
