<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use DateTimeInterface;
use PhpSoftBox\Orm\Metadata\PropertyMetadata;

final readonly class ResolvedTimestampColumn
{
    public function __construct(
        public PropertyMetadata $column,
        public DateTimeInterface $value,
    ) {
    }
}
