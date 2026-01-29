<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use PhpSoftBox\Orm\Behavior\Command\OnCreate;

final class EventEntityListener
{
    #[Listen(OnCreate::class)]
    public function onCreate(OnCreate $event): void
    {
        // Переписываем name, чтобы убедиться, что listener реально отработал.
        $event->state()->register('name', 'from_listener');
    }
}

