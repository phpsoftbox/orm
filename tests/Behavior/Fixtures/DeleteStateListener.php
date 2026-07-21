<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use PhpSoftBox\Orm\Behavior\Command\OnDelete;

/**
 * Пишет в состояние удаления колонки, заданные в конструкторе.
 */
final readonly class DeleteStateListener
{
    /**
     * @param array<string, mixed> $columns
     */
    public function __construct(
        private array $columns,
    ) {
    }

    #[Listen(OnDelete::class)]
    public function onDelete(OnDelete $event): void
    {
        foreach ($this->columns as $column => $value) {
            $event->state()->register($column, $value);
        }
    }
}
