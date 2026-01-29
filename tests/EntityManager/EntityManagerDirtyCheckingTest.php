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
final class EntityManagerDirtyCheckingTest extends TestCase
{
    /**
     * Проверяет, что после find() делается snapshot через AutoEntityMapper,
     * и при изменении сущности + persist() + flush() вызывается UPDATE.
     */
    #[Test]
    public function findThenModifyThenPersistTriggersUpdate(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('transaction')->willReturnCallback(static fn (callable $fn) => $fn());

        $persister = $this->createMock(EntityPersisterInterface::class);
        $persister->expects(self::never())->method('insert');
        $persister->expects(self::once())->method('update');
        $persister->expects(self::never())->method('delete');

        $em = new EntityManager(
            connection: $connection,
            unitOfWork: new InMemoryUnitOfWork(),
            persister: $persister,
        );

        $entity = new UserMappedEntity(1, 'John');
        $em->registerRepository(UserMappedEntity::class, new StubUserMappedRepository($entity));

        /** @var UserMappedEntity $loaded */
        $loaded = $em->find(UserMappedEntity::class, 1);
        self::assertNotNull($loaded);

        // меняем после snapshot
        $loaded->name = 'Kate';

        $em->persist($loaded);
        $em->flush();
    }
}

