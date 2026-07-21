<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PhpSoftBox\Orm\Tests\Fixtures\User;
use PhpSoftBox\Orm\UnitOfWork\EntityState;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[CoversClass(UnitOfWork::class)]
final class SmartUnitOfWorkTest extends TestCase
{
    /**
     * Проверяет, что при id === null сущность считается новой без обращений к репозиторию.
     */
    #[Test]
    public function idNullIsNewWithoutRepositoryCall(): void
    {
        $uow = new UnitOfWork();

        $repo = $this->createMock(EntityRepositoryInterface::class);
        $repo->expects(self::never())->method('exists');

        $entity = new class () implements EntityInterface {
            public function id(): int|UuidInterface|null
            {
                return null;
            }
        };

        self::assertSame(EntityState::New, $uow->resolveForPersist($entity, $repo));
    }

    /**
     * Проверяет, что при id !== null и отсутствии записи в БД сущность считается новой.
     */
    #[Test]
    public function idNotNullButNotExistsIsNew(): void
    {
        $uow = new UnitOfWork();

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
        $uow = new UnitOfWork();

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
        $uow = new UnitOfWork();

        $id   = Uuid::uuid7();
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
