<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata;

/**
 * Описание хука, объявленного через #[Behavior\Hook].
 */
final readonly class HookMetadata
{
    /**
     * @param callable $callable
     * @param list<class-string> $events
     */
    public function __construct(
        public mixed $callable,
        public array $events,
    ) {
    }
}
