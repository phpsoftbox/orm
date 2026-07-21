<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}
