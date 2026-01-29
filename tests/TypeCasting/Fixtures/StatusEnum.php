<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\TypeCasting\Fixtures;

enum StatusEnum: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
