<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use PhpSoftBox\Orm\Contracts\ListenerResolverInterface;

final readonly class DefaultListenerResolver implements ListenerResolverInterface
{
    public function resolve(string $class): object
    {
        return new $class();
    }
}

