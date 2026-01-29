<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Options;

enum JsonInvalidPolicy: string
{
    case Empty = 'empty';
    case Null  = 'null';
    case Throw = 'throw';
}
