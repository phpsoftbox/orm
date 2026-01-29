<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Persistence\EntityPersisterInterface;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\StubUserMappedRepository;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\UserMappedEntity;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class EntityManagerDirtyCheckingNoChangesTest extends TestCase
{
    /**
     * Проверяет, что если сущность была загружена через find() (snapshot сделан),
     * но не изменялась, то persist() + flush() не вызовут UPDATE.
     */
    #[Test]
    public function findThenPersistWithoutChangesDoesNotTriggerUpdate(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('transaction')->willReturnCallback(static fn (callable $fn) => $fn());

        $persister = $this->createMock(EntityPersisterInterface::class);
        $persister->expects(self::never())->method('insert');
        $persister->expects(self::never())->method('update');
        $persister->expects(self::never())->method('delete');

        $em = new EntityManager(
            connection: $connection,
            unitOfWork: new InMemoryUnitOfWork(),
            persister: $persister,
        );

        $entity = new UserMappedEntity(1, 'John');
        $em->registerRepository(UserMappedEntity::class, new StubUserMappedRepository($entity));

        $loaded = $em->find(UserMappedEntity::class, 1);
        self::assertNotNull($loaded);

        // не меняем
        $em->persist($loaded);
        $em->flush();
    }
}

