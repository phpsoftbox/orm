<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting;

use InvalidArgumentException;
use PhpSoftBox\Orm\TypeCasting\Contracts\TypeCasterInterface;
use PhpSoftBox\Orm\TypeCasting\Contracts\TypeHandlerInterface;

use function array_key_exists;
use function array_unshift;
use function array_values;
use function is_a;

/**
 * Универсальный TypeCaster (не ORM, не БД).
 */
class TypeCaster implements TypeCasterInterface
{
    /**
     * @var list<TypeHandlerInterface>
     */
    protected array $handlers;

    /**
     * @param list<TypeHandlerInterface> $handlers
     */
    public function __construct(array $handlers = [])
    {
        $this->handlers = array_values($handlers);
    }

    public function handlers(): array
    {
        return $this->handlers;
    }

    public function registerHandler(TypeHandlerInterface $handler): void
    {
        array_unshift($this->handlers, $handler);
    }

    public function cast(string $type, mixed $value): mixed
    {
        // Если передали class-string handler'а — применяем его напрямую.
        if (is_a($type, TypeHandlerInterface::class, true)) {
            $handler = new $type();

            return $handler->cast($value);
        }

        foreach ($this->handlers as $handler) {
            if ($handler->supports($type)) {
                return $handler->cast($value);
            }
        }

        throw new InvalidArgumentException('No type handler registered for type: ' . $type);
    }

    public function castArray(array $config, array $data): array
    {
        $result = $data;

        foreach ($config as $key => $type) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $result[$key] = $this->cast($type, $data[$key]);
        }

        return $result;
    }
}
