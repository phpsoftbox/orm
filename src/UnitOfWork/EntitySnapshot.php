<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\UnitOfWork;

/**
 * Снапшот состояния сущности для dirty-checking.
 *
 * Храним данные в нормализованном виде (скаляры/массивы скаляров),
 * чтобы сравнение было предсказуемым.
 */
final readonly class EntitySnapshot
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public array $data,
    ) {
    }
}
