<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\UnitOfWork;

enum EntityState: string
{
    case New     = 'new';
    case Managed = 'managed';
    case Removed = 'removed';
}
