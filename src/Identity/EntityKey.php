<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Identity;

use InvalidArgumentException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use Ramsey\Uuid\UuidInterface;

use function is_int;

final readonly class EntityKey
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public string $class,
        public int|string $id,
    ) {
    }

    /**
     * @param class-string $class
     */
    public static function fromParts(string $class, int|string|UuidInterface $id): self
    {
        return new self($class, $id instanceof UuidInterface ? $id->toString() : $id);
    }

    public static function fromEntity(EntityInterface $entity): self
    {
        $id = $entity->id();
        if ($id === null) {
            throw new InvalidArgumentException('Cannot create EntityKey from entity without id().');
        }

        return self::fromParts($entity::class, $id);
    }

    public function toString(): string
    {
        return $this->class . '#' . (is_int($this->id) ? (string) $this->id : $this->id);
    }
}
