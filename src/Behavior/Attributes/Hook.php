<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Hook
{
    /**
     * @param callable $callable
     * @param list<class-string> $events
     */
    public function __construct(
        public mixed $callable,
        public array $events,
    ) {
    }
}
