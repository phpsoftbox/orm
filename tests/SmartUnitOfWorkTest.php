<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PhpSoftBox\Orm\IdentityMap\WeakIdentityMap;
use PhpSoftBox\Orm\Tests\Fixtures\User;
use PhpSoftBox\Orm\UnitOfWork\EntityState;
use PhpSoftBox\Orm\UnitOfWork\AdvancedUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(AdvancedUnitOfWork::class)]
final class SmartUnitOfWorkTest extends TestCase
{
    /**
     * Проверяет, что при id === null сущность считается новой без обращений к репозиторию.
     */
    #[Test]
    public function idNullIsNewWithoutRepositoryCall(): void
    {
        $identityMap = new WeakIdentityMap();
        $uow = new AdvancedUnitOfWork($identityMap);

        $repo = $this->createMock(EntityRepositoryInterface::class);
        $repo->expects(self::never())->method('exists');

        $entity = new class implements \PhpSoftBox\Orm\Contracts\EntityInterface {
            public function id(): int|\Ramsey\Uuid\UuidInterface|null { return null; }
        };

        self::assertSame(EntityState::New, $uow->resolveForPersist($entity, $repo));
    }

    /**
     * Проверяет, что при id !== null и отсутствии записи в БД сущность считается новой.
     */
    #[Test]
    public function idNotNullButNotExistsIsNew(): void
    {
        $identityMap = new WeakIdentityMap();
        $uow = new AdvancedUnitOfWork($identityMap);

        $id = Uuid::uuid7();

        $repo = $this->createMock(EntityRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('exists')
            ->with($id)
            ->willReturn(false);

        $user = new User($id, 'u');

        self::assertSame(EntityState::New, $uow->resolveForPersist($user, $repo));
    }

    /**
     * Проверяет, что при id !== null и наличии записи в БД сущность считается managed.
     */
    #[Test]
    public function idNotNullAndExistsIsManaged(): void
    {
        $identityMap = new WeakIdentityMap();
        $uow = new AdvancedUnitOfWork($identityMap);

        $id = Uuid::uuid7();

        $repo = $this->createMock(EntityRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('exists')
            ->with($id)
            ->willReturn(true);

        $user = new User($id, 'u');

        self::assertSame(EntityState::Managed, $uow->resolveForPersist($user, $repo));
    }

    /**
     * Проверяет, что результат exists кэшируется и репозиторий не вызывается повторно для того же id.
     */
    #[Test]
    public function existsIsCached(): void
    {
        $identityMap = new WeakIdentityMap();
        $uow = new AdvancedUnitOfWork($identityMap);

        $id = Uuid::uuid7();
        $user = new User($id, 'u');

        $repo = $this->createMock(EntityRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('exists')
            ->with($id)
            ->willReturn(true);

        self::assertSame(EntityState::Managed, $uow->resolveForPersist($user, $repo));
        self::assertSame(EntityState::Managed, $uow->resolveForPersist($user, $repo));
    }
}


