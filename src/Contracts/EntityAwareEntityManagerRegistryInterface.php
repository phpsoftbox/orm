<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

interface EntityAwareEntityManagerRegistryInterface extends EntityManagerRegistryInterface
{
    /**
     * Возвращает имя connection из metadata entity или null для default connection.
     *
     * @param class-string $entityClass
     */
    public function connectionNameForEntity(string $entityClass): ?string;

    /**
     * @param class-string $entityClass
     */
    public function forEntity(string $entityClass, bool $write = true): EntityManagerInterface;
}
