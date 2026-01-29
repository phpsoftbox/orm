<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Contracts;

interface OrmTypeHandlerInterface extends TypeHandlerInterface
{
    /**
     * Преобразует значение в "скаляр" (то, что можно отправить в БД).
     *
     * @param mixed $value
     * @param array $options
     * @return int|float|string|bool|null
     */
    public function castTo(mixed $value, array $options = []): int|float|string|bool|null;

    /**
     * Преобразует значение из "скаляра" (пришло из БД) в PHP-тип.
     */
    public function castFrom(mixed $value, array $options = []): mixed;
}
