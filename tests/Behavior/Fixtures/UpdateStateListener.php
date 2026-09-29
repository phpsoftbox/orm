<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use PhpSoftBox\Orm\Behavior\Command\OnUpdate;

/**
 * Пишет в состояние обновления колонки, заданные в конструкторе.
 */
final readonly class UpdateStateListener
{
    /**
     * @param array<string, mixed> $columns
     */
    public function __construct(
        private array $columns,
    ) {
    }

    #[Listen(OnUpdate::class)]
    public function onUpdate(OnUpdate $event): void
    {
        foreach ($this->columns as $column => $value) {
            $event->state()->register($column, $value);
        }
    }
}
