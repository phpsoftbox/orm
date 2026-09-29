<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

/**
 * Зависимость listener'а, которую должен предоставить DI-контейнер.
 */
final readonly class ListenerNameSource
{
    public function __construct(
        public string $name,
    ) {
    }
}
