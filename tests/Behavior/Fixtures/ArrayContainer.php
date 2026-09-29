<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use Psr\Container\ContainerInterface;
use RuntimeException;

use function array_key_exists;

/**
 * Минимальный PSR-11 контейнер для тестов.
 */
final readonly class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $entries
     */
    public function __construct(
        private array $entries,
    ) {
    }

    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->entries)) {
            throw new RuntimeException('Entry not found: ' . $id);
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
