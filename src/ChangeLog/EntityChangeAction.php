<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\ChangeLog;

enum EntityChangeAction: string
{
    case Create      = 'create';
    case Update      = 'update';
    case Delete      = 'delete';
    case ForceDelete = 'force_delete';
    case Restore     = 'restore';
}
