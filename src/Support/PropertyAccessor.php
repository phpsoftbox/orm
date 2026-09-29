<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Support;

use InvalidArgumentException;
use ReflectionProperty;

use function property_exists;

/**
 * Доступ к свойствам сущностей через Reflection.
 *
 * Позволяет ORM читать и записывать свойства любой видимости, включая `readonly`:
 * неинициализированное readonly-свойство инициализируется, а уже инициализированное остаётся неизменным.
 */
final class PropertyAccessor
{
    /**
     * @var array<string, ReflectionProperty>
     */
    private static array $properties = [];

    /**
     * Проверяет, что у объекта есть свойство (объявленное или динамическое).
     */
    public static function has(object $object, string $property): bool
    {
        return property_exists($object, $property);
    }

    /**
     * Возвращает значение свойства; для неинициализированного свойства возвращает null.
     */
    public static function read(object $object, string $property): mixed
    {
        $reflection = self::reflection($object, $property);
        if ($reflection === null) {
            if (!property_exists($object, $property)) {
                throw new InvalidArgumentException('Property does not exist: ' . $object::class . '::$' . $property);
            }

            return $object->{$property};
        }

        return $reflection->isInitialized($object) ? $reflection->getValue($object) : null;
    }

    public static function isInitialized(object $object, string $property): bool
    {
        $reflection = self::reflection($object, $property);
        if ($reflection === null) {
            return property_exists($object, $property);
        }

        return $reflection->isInitialized($object);
    }

    /**
     * Записывает значение свойства.
     *
     * Инициализированное readonly-свойство не меняется (возвращается false): ORM не нарушает неизменяемость.
     *
     * @return bool true, если значение записано
     */
    public static function write(object $object, string $property, mixed $value): bool
    {
        $reflection = self::reflection($object, $property);
        if ($reflection === null) {
            if (!property_exists($object, $property)) {
                throw new InvalidArgumentException('Property does not exist: ' . $object::class . '::$' . $property);
            }

            $object->{$property} = $value;

            return true;
        }

        if ($reflection->isReadOnly() && $reflection->isInitialized($object)) {
            return false;
        }

        $reflection->setValue($object, $value);

        return true;
    }

    public static function isReadOnly(object $object, string $property): bool
    {
        return self::reflection($object, $property)?->isReadOnly() ?? false;
    }

    /**
     * Reflection объявленного свойства или null для динамического/отсутствующего свойства.
     */
    private static function reflection(object $object, string $property): ?ReflectionProperty
    {
        $key = $object::class . '::' . $property;
        if (isset(self::$properties[$key])) {
            return self::$properties[$key];
        }

        if (!property_exists($object::class, $property)) {
            return null;
        }

        return self::$properties[$key] = new ReflectionProperty($object::class, $property);
    }
}
