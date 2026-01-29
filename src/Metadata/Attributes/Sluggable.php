<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Sluggable
{
    public function __construct(
        public string $source,
        public string $target,
        public bool $onUpdate = false,
        public string $prefix = '',
        public string $postfix = '',
    ) {
    }
}
