<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use PhpSoftBox\Orm\Behavior\Command\EntityCommandInterface;
use ReflectionClass;

use function is_callable;
use function str_starts_with;

/**
 * Простой синхронный диспетчер событий ORM.
 *
 * Делает две вещи:
 * 1) вызывает модульные хуки, которые передал внешний код через registerListenerObject()
 * 2) отражением ищет методы с #[Listen(...)] в зарегистрированных listener-объектах
 */
final class DefaultEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var list<object>
     */
    private array $listenerObjects = [];

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

    public function dispatch(EntityCommandInterface $event): void
    {
        $eventClass = $event::class;

        foreach ($this->listenerObjects as $listener) {
            $rc = new ReflectionClass($listener);

            foreach ($rc->getMethods() as $method) {
                foreach ($method->getAttributes(Listen::class) as $attr) {
                    /** @var Listen $listen */
                    $listen = $attr->newInstance();

                    if ($listen->event !== $eventClass) {
                        continue;
                    }

                    $callable = [$listener, $method->getName()];
                    if (is_callable($callable)) {
                        $callable($event);
                    }
                }

                // (если уже есть reflection-dispatch по имени метода/атрибутам - изменений не будет)
                if (str_starts_with($method->getName(), 'on') && $method->getNumberOfParameters() === 1) {
                    $param = $method->getParameters()[0];

                    if ($param->getType() && $param->getType()->getName() === $eventClass) {
                        $callable = [$listener, $method->getName()];
                        if (is_callable($callable)) {
                            $callable($event);
                        }
                    }
                }
            }
        }
    }
}
