<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting;

use InvalidArgumentException;
use PhpSoftBox\Orm\TypeCasting\Contracts\OrmTypeCasterInterface;
use PhpSoftBox\Orm\TypeCasting\Contracts\OrmTypeHandlerInterface;
use PhpSoftBox\Orm\TypeCasting\Contracts\TypeHandlerInterface;

use function array_key_exists;
use function is_a;

/**
 * ORM-адаптер для TypeCasting.
 */
final class OrmTypeCaster extends TypeCaster implements OrmTypeCasterInterface
{
    /**
     * @param list<OrmTypeHandlerInterface> $handlers
     */
    public function __construct(array $handlers)
    {
        parent::__construct($handlers);
    }

    public function registerHandler(TypeHandlerInterface $handler): void
    {
        if (!$handler instanceof OrmTypeHandlerInterface) {
            throw new InvalidArgumentException('OrmTypeCaster expects OrmTypeHandlerInterface handlers.');
        }

        parent::registerHandler($handler);
    }

    public function castArray(array $config, array $data): array
    {
        // В ORM castArray трактуем как castFrom().
        $result = $data;

        foreach ($config as $key => $typeOrHandler) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            if (is_a($typeOrHandler, OrmTypeHandlerInterface::class, true)) {
                $handler = new $typeOrHandler();
                $result[$key] = $handler->castFrom($data[$key]);
                continue;
            }

            $result[$key] = $this->castFrom($typeOrHandler, $data[$key]);
        }

        return $result;
    }

    public function castTo(string $type, mixed $value, array $options = []): int|float|string|bool|null
    {
        $handler = $this->resolveHandler($type);
        return $handler->castTo($value, $options);
    }

    public function castFrom(string $type, mixed $value, array $options = []): mixed
    {
        $handler = $this->resolveHandler($type);
        return $handler->castFrom($value, $options);
    }

    private function resolveHandler(string $type): OrmTypeHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof OrmTypeHandlerInterface && $handler->supports($type)) {
                return $handler;
            }
        }

        throw new InvalidArgumentException('No ORM type handler registered for type: ' . $type);
    }
}
