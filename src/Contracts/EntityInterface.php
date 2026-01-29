<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use Ramsey\Uuid\UuidInterface;

/**
 * Базовый контракт для ORM-сущностей.
 *
 * Идентификатор может быть числовым (AI) или UUID.
 */
interface EntityInterface
{
    /**
     * Возвращает идентификатор сущности.
     *
     * - Для автоинкремента: null до вставки, int после вставки.
     * - Для UUID: как правило UuidInterface может быть задан ��разу.
     */
    public function id(): int|UuidInterface|null;
}
