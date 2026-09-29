<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use PhpSoftBox\Orm\Behavior\Command\OnCreate;

/**
 * Метод подходит и под #[Listen], и под соглашение `on*`.
 */
final class CountingAttributeListener
{
    public int $calls = 0;

    #[Listen(OnCreate::class)]
    public function onCreate(OnCreate $event): void
    {
        $this->calls++;
    }
}
