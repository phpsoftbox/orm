<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Bulk\Fixtures;

enum BulkStatus: string
{
    case Active   = 'active';
    case Archived = 'archived';
}
