<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use function str_starts_with;

/**
 * Находит методы listener-объекта, которые должны обработать событие.
 *
 * Правила:
 * - метод с #[Listen(Event::class)] вызывается только для перечисленных в атрибутах событий;
 * - соглашение `on*(Event $event)` применяется только к публичным методам без #[Listen],
 *   поэтому метод с атрибутом не вызывается повторно по имени.
 */
final class ListenerMethodResolver
{
    /**
     * @var array<class-string, array<class-string, list<string>>>
     */
    private static array $cache = [];

    /**
     * Вызывает все подходящие методы listener'а для события.
     */
    public static function invoke(object $listener, object $event): void
    {
        foreach (self::methodsFor($listener::class, $event::class) as $method) {
            $listener->{$method}($event);
        }
    }

    /**
     * @param class-string $listenerClass
     * @param class-string $eventClass
     * @return list<string>
     */
    public static function methodsFor(string $listenerClass, string $eventClass): array
    {
        $map = self::$cache[$listenerClass] ??= self::buildMap($listenerClass);

        return $map[$eventClass] ?? [];
    }

    /**
     * @param class-string $listenerClass
     * @return array<class-string, list<string>>
     */
    private static function buildMap(string $listenerClass): array
    {
        $map = [];

        $reflection = new ReflectionClass($listenerClass);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                continue;
            }

            $attributes = $method->getAttributes(Listen::class);
            if ($attributes !== []) {
                foreach ($attributes as $attribute) {
                    /** @var Listen $listen */
                    $listen = $attribute->newInstance();

                    $map[$listen->event] ??= [];
                    $map[$listen->event][] = $method->getName();
                }

                continue;
            }

            if (!str_starts_with($method->getName(), 'on') || $method->getNumberOfParameters() !== 1) {
                continue;
            }

            $type = $method->getParameters()[0]->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            /** @var class-string $eventClass */
            $eventClass = $type->getName();

            $map[$eventClass] ??= [];
            $map[$eventClass][] = $method->getName();
        }

        return $map;
    }
}
