<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use PhpSoftBox\Orm\Behavior\Command\EntityCommandInterface;

interface EventDispatcherInterface
{
    public function dispatch(EntityCommandInterface $event): void;
}
