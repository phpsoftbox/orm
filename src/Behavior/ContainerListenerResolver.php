<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use PhpSoftBox\Orm\Contracts\ListenerResolverInterface;
use Psr\Container\ContainerInterface;

/**
 * Резолвер listeners через PSR-11 контейнер.
 *
 * Listener берётся из контейнера (`$container->get($class)`), поэтому может иметь зависимости в конструкторе.
 * Если контейнер не знает класс, используется резолвер-фолбэк (по умолчанию {@see DefaultListenerResolver}).
 */
final readonly class ContainerListenerResolver implements ListenerResolverInterface
{
    public function __construct(
        private ContainerInterface $container,
        private ListenerResolverInterface $fallback = new DefaultListenerResolver(),
    ) {
    }

    public function resolve(string $class): object
    {
        if ($this->container->has($class)) {
            return $this->container->get($class);
        }

        return $this->fallback->resolve($class);
    }
}
