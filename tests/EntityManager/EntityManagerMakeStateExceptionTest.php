<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use InvalidArgumentException;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Persistence\EntityPersisterInterface;
use PhpSoftBox\Orm\Repository\GenericEntityRepository;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\UnsupportedTypeEntity;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class EntityManagerMakeStateExceptionTest extends TestCase
{
    /**
     * Проверяет, что flush не проглатывает ошибку extract/cast в makeState.
     */
    #[Test]
    public function flushThrowsWhenMakeStateExtractionFails(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('transaction')->willReturnCallback(static fn (callable $fn) => $fn());

        $persister = $this->createMock(EntityPersisterInterface::class);
        $persister->expects($this->never())->method('insert');

        $em = new EntityManager(
            connection: $connection,
            unitOfWork: new UnitOfWork(),
            persister: $persister,
        );

        $em->registerRepository(
            UnsupportedTypeEntity::class,
            new GenericEntityRepository($connection, UnsupportedTypeEntity::class),
        );

        $em->persist(new UnsupportedTypeEntity(null, 'Title'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No type handler registered for type: unknown_type');

        $em->flush();
    }
}
