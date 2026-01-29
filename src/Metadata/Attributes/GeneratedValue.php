<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class GeneratedValue
{
    /**
     * @param 'auto'|'uuid'|'none' $strategy
     */
    public function __construct(
        public string $strategy = 'none',
    ) {
    }
}

