<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Listen
{
    /**
     * @param class-string $event
     */
    public function __construct(
        public string $event,
    ) {
    }
}
