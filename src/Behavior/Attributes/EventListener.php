<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class EventListener
{
    /**
     * @param class-string $listener
     */
    public function __construct(
        public string $listener,
    ) {
    }
}
