<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Behavior\Command\OnCreate;

/**
 * Listener без #[Listen]: вызывается по соглашению `on*`.
 */
final class CountingConventionListener
{
    public int $calls = 0;

    public function onCreate(OnCreate $event): void
    {
        $this->calls++;
    }
}
