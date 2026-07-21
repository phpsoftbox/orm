<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Bulk;

enum BulkWriteAction: string
{
    case Update      = 'update';
    case Remove      = 'remove';
    case ForceRemove = 'force_remove';
    case Restore     = 'restore';
}
