<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

/**
 * Простой синхронный диспетчер событий ORM.
 *
 * Вызывает у зарегистрированных listener-объектов методы, подходящие под событие:
 * - методы с #[Listen(Event::class)];
 * - методы `on*(Event $event)` без атрибута #[Listen] (соглашение по имени).
 *
 * Один метод вызывается на событие не более одного раза: если у метода есть #[Listen],
 * соглашение по имени для него не применяется.
 */
final class DefaultEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var list<object>
     */
    private array $listenerObjects = [];

    /**
     * @param iterable<object> $listenerObjects
     */
    public function __construct(
        iterable $listenerObjects = [],
    ) {
        foreach ($listenerObjects as $obj) {
            $this->registerListenerObject($obj);
        }
    }

    public function registerListenerObject(object $listener): void
    {
        $this->listenerObjects[] = $listener;
    }

    public function dispatch(object $event): void
    {
        foreach ($this->listenerObjects as $listener) {
            ListenerMethodResolver::invoke($listener, $event);
        }
    }
}
