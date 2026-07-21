<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Persistence\EntityPersisterInterface;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;

/**
 * Расширенный контракт EntityManager, который раскрывает контекст для фабрики репозиториев.
 */
interface EntityManagerContextInterface extends EntityManagerInterface
{
    public function metadata(): MetadataProviderInterface;

    public function mapper(): AutoEntityMapper;

    public function persister(): EntityPersisterInterface;
}
