<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Result;

use ArrayAccess;
use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use ReflectionObject;

use function array_key_exists;
use function is_string;
use function method_exists;

/**
 * Результат ORM-запроса, где рядом с сущностью нужны вычисленные поля.
 *
 * @template TEntity of EntityInterface
 * @implements ArrayAccess<string, mixed>
 */
final readonly class EntityResult implements ArrayAccess
{
    /**
     * @param TEntity $entity
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public EntityInterface $entity,
        public array $extra = [],
    ) {
    }

    public function extra(string $key, mixed $default = null): mixed
    {
        return $this->extra[$key] ?? $default;
    }

    public function __get(string $key): mixed
    {
        if ($this->hasEntityAttribute($key)) {
            return $this->entity->{$key};
        }

        return $this->extra[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return $this->hasEntityAttribute($key) || array_key_exists($key, $this->extra);
    }

    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            return false;
        }

        return isset($this->{$offset});
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset)) {
            return null;
        }

        return $this->{$offset};
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('EntityResult is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('EntityResult is read-only.');
    }

    private function hasEntityAttribute(string $key): bool
    {
        if (method_exists($this->entity, '__isset') && isset($this->entity->{$key})) {
            return true;
        }

        $reflection = new ReflectionObject($this->entity);

        if (!$reflection->hasProperty($key)) {
            return false;
        }

        return $reflection->getProperty($key)->isPublic();
    }
}
