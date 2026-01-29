<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use Ramsey\Uuid\UuidInterface;

/**
 * Контракт идентификатора сущности.
 *
 * Нужен для расширения ORM под составные первичные ключи (composite PK).
 *
 * Сейчас базовые репозитории поддерживают только single primary key,
 * но этот интерфейс позволяет заранее заложить правильную архитектуру.
 */
interface IdentifierInterface
{
    /**
     * Возвращает значения идентификатора как ассоциативный массив.
     *
     * Ключи массива — имена колонок, значения — их значения.
     *
     * @return array<string, int|string|UuidInterface|null>
     */
    public function values(): array;
}

