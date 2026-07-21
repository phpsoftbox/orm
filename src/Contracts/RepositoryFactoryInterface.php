<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

interface RepositoryFactoryInterface
{
    /**
     * Создаёт репозиторий для указанной сущности.
     *
     * @param class-string $entityClass
     */
    public function create(string $entityClass, EntityManagerInterface $em): RepositoryInterface;
}
