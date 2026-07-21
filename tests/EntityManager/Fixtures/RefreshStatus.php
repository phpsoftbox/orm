<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

enum RefreshStatus: string
{
    case Active   = 'active';
    case Disabled = 'disabled';
}
