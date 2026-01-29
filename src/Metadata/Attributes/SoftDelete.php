<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SoftDelete
{
    public function __construct(
        public string $entityField,
        public string $column = 'deleted_datetime',
    ) {
    }
}
