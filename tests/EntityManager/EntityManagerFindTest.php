<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\IdentityMap\WeakIdentityMap;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\SimpleEntity;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\SimpleEntityRepository;
use PhpSoftBox\Orm\UnitOfWork\AdvancedUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class EntityManagerFindTest extends TestCase
{
    /**
     * Проверяет, что при SmartUnitOfWork повторный find() возвращает тот же объект из IdentityMap,
     * и репозиторий не вызывается повторно.
     */
    #[Test]
    public function findUsesIdentityMap(): void
    {
        $connection = $this->createStub(ConnectionInterface::class);

        $uow = new AdvancedUnitOfWork(new WeakIdentityMap());

        $em = new EntityManager($connection, $uow);

        $entity = new SimpleEntity(1, 'John');

        $repo = new SimpleEntityRepository([1 => $entity]);

        $em->registerRepository(SimpleEntity::class, $repo);

        $a = $em->find(SimpleEntity::class, 1);
        $b = $em->find(SimpleEntity::class, 1);

        self::assertSame($entity, $a);
        self::assertSame($entity, $b);
        self::assertSame(1, $repo->findCalls);
    }
}
