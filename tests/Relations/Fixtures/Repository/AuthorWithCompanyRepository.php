<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\AuthorWithCompany;

/**
 * @extends AbstractEntityRepository<AuthorWithCompany>
 */
final class AuthorWithCompanyRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return AuthorWithCompany::class;
    }

    protected function table(): string
    {
        return 'authors2';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new AuthorWithCompany(
            id: (int) $row['id'],
            name: (string) $row['name'],
            companyId: (int) $row['company_id'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var AuthorWithCompany $entity */
        return [
            'id'         => $entity->id,
            'name'       => $entity->name,
            'company_id' => $entity->companyId,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
    }

    protected function doRemove(EntityInterface $entity): void
    {
    }
}
