<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use LogicException;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\CompanyWithPostsThroughAuthors;

/**
 * @extends AbstractEntityRepository<CompanyWithPostsThroughAuthors>
 */
final class CompanyWithPostsThroughAuthorsRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'companies_hmt';
    }

    protected function entityClass(): string
    {
        return CompanyWithPostsThroughAuthors::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new CompanyWithPostsThroughAuthors(
            id: (int) $row['id'],
            name: (string) $row['name'],
            posts: new EntityCollection([]),
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var CompanyWithPostsThroughAuthors $entity */
        return [
            'id' => $entity->id,
            'name' => $entity->name,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        throw new LogicException('CompanyWithPostsThroughAuthorsRepository fixture is read-only.');
    }

    protected function doRemove(EntityInterface $entity): void
    {
        throw new LogicException('CompanyWithPostsThroughAuthorsRepository fixture is read-only.');
    }
}
