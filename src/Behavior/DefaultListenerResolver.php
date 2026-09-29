<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use PhpSoftBox\Orm\Contracts\ListenerResolverInterface;
use PhpSoftBox\Orm\Exception\OrmException;
use ReflectionClass;
use Throwable;

use function class_exists;

/**
 * Резолвер listeners по умолчанию: создаёт listener через `new $class()`.
 *
 * Подходит только для listeners без обязательных аргументов конструктора. Для listeners с зависимостями
 * используйте {@see ContainerListenerResolver} или собственную реализацию {@see ListenerResolverInterface}.
 */
final readonly class DefaultListenerResolver implements ListenerResolverInterface
{
    public function resolve(string $class): object
    {
        if (!class_exists($class)) {
            throw new OrmException('ORM listener class does not exist: ' . $class . '.');
        }

        $constructor = new ReflectionClass($class)->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new OrmException(
                'ORM listener ' . $class . ' has required constructor dependencies and cannot be created by '
                . self::class . '. Pass ContainerListenerResolver (or another ListenerResolverInterface) to EntityManager.',
            );
        }

        try {
            return new $class();
        } catch (Throwable $e) {
            throw new OrmException('Cannot create ORM listener ' . $class . ': ' . $e->getMessage(), 0, $e);
        }
    }
}
