<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use Closure;

/**
 * Dispatcher-адаптер над замыканием.
 *
 * Используется EntityManager, чтобы передать в bulk writer адресную доставку событий
 * (`#[EventListener]` сущности + глобальный dispatcher).
 */
final readonly class CallbackEventDispatcher implements EventDispatcherInterface
{
    /**
     * @param Closure(object): void $dispatch
     */
    public function __construct(
        private Closure $dispatch,
    ) {
    }

    public function dispatch(object $event): void
    {
        ($this->dispatch)($event);
    }
}
