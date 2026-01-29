<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

interface RepositoryInterface
{
    /**
     * Сохраняет (вставляет/обновляет) сущность.
     */
    public function persist(EntityInterface $entity): void;

    /**
     * Удаляет сущность.
     */
    public function remove(EntityInterface $entity): void;
}
