<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Persistence\EntityPersisterInterface;
use PhpSoftBox\Orm\Repository\GenericEntityRepository;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\TestEntity;

#[CoversClass(EntityManager::class)]
final class EntityManagerFlushUsesPersisterTest extends TestCase
{
    /**
     * Проверяет, что если репозиторий не умеет сохранять (GenericEntityRepository), то flush() использует persister.
     */
    #[Test]
    public function flushUsesPersisterWhenRepositoryIsGeneric(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('transaction')->willReturnCallback(static fn (callable $fn) => $fn());

        $persister = $this->createMock(EntityPersisterInterface::class);
        $persister->expects(self::once())->method('insert');
        $persister->expects(self::never())->method('update');
        $persister->expects(self::never())->method('delete');

        $em = new EntityManager(
            connection: $connection,
            unitOfWork: new InMemoryUnitOfWork(),
            persister: $persister,
        );

        $em->registerRepository(TestEntity::class, new GenericEntityRepository($connection, TestEntity::class));

        $entity = new TestEntity(null);
        $em->persist($entity);
        $em->flush();
    }
}
