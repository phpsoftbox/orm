<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use PhpSoftBox\Orm\Behavior\Command\OnCreate;

/**
 * Listener с обязательной зависимостью в конструкторе.
 */
final readonly class DependentListener
{
    public function __construct(
        private ListenerNameSource $source,
    ) {
    }

    #[Listen(OnCreate::class)]
    public function onCreate(OnCreate $event): void
    {
        $event->state()->register('name', $this->source->name);
    }
}
