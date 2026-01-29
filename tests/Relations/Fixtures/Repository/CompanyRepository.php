<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Company;

/**
 * @extends AbstractEntityRepository<Company>
 */
final class CompanyRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return Company::class;
    }

    protected function table(): string
    {
        return 'companies';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new Company(
            id: (int) $row['id'],
            name: (string) $row['name'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var Company $entity */
        return [
            'id' => $entity->id,
            'name' => $entity->name,
        ];
    }

    protected function doPersist(EntityInterface $entity): void {}

    protected function doRemove(EntityInterface $entity): void {}
}

