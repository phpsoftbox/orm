<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior\Command;

/**
 * Состояние сущности для сценариев "до сохранения".
 *
 * Хранит данные в формате "колонка => значение". Слушатели могут менять данные
 * через register(), не меняя сам объект сущности напрямую.
 */
final class MutableEntityState
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
}

