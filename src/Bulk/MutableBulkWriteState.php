<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Bulk;

final class MutableBulkWriteState
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function register(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function replace(array $data): void
    {
        $this->data = $data;
    }
}
