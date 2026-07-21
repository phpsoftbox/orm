<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PhpSoftBox\Orm\Tests\Fixtures\EntityWithNullableIntId;
use PhpSoftBox\Orm\UnitOfWork\EntityState;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnitOfWork::class)]
final class UnitOfWorkSchedulingTest extends TestCase
{
    /**
     * Проверяет, что NEW сущность планируется на INSERT.
     */
    #[Test]
    public function newEntityIsScheduledForInsert(): void
    {
        $uow = new UnitOfWork();

        $e = new EntityWithNullableIntId(null);

        $uow->markNew($e);
        $uow->schedulePersist($e);

        self::assertSame([$e], $uow->scheduledInserts());
        self::assertSame([], $uow->scheduledUpdates());
        self::assertSame([], $uow->scheduledDeletes());
    }

    /**
     * Проверяет, что NEW + remove до flush схлопывается в no-op.
     */
    #[Test]
    public function newEntityInsertThenRemoveIsNoOp(): void
    {
        $uow = new UnitOfWork();

        $e = new EntityWithNullableIntId(null);

        $uow->markNew($e);
        $uow->schedulePersist($e);

        $uow->markRemoved($e);
        $uow->scheduleRemove($e);

        self::assertSame([], $uow->scheduledInserts());
        self::assertSame([], $uow->scheduledUpdates());
        self::assertSame([], $uow->scheduledDeletes());

        self::assertSame(EntityState::Removed, $uow->state($e));
    }

    /**
     * Проверяет, что MANAGED сущность планируется как UPDATE, а remove схлопывает update в delete.
     */
    #[Test]
    public function managedEntityUpdateThenRemoveBecomesDelete(): void
    {
        $uow = new UnitOfWork();

        $e = new EntityWithNullableIntId(10);

        $uow->markManaged($e);
        $uow->schedulePersist($e);

        self::assertSame([], $uow->scheduledInserts());
        self::assertSame([$e], $uow->scheduledUpdates());
        self::assertSame([], $uow->scheduledDeletes());

        $uow->markRemoved($e);
        $uow->scheduleRemove($e);

        self::assertSame([], $uow->scheduledInserts());
        self::assertSame([], $uow->scheduledUpdates());
        self::assertSame([$e], $uow->scheduledDeletes());
    }

    /**
     * Проверяет, что remove() затем persist() отменяет delete и планирует update.
     */
    #[Test]
    public function removeThenPersistCancelsDelete(): void
    {
        $uow = new UnitOfWork();

        $e = new EntityWithNullableIntId(10);

        $uow->markManaged($e);

        $uow->markRemoved($e);
        $uow->scheduleRemove($e);

        self::assertSame([$e], $uow->scheduledDeletes());

        $uow->markManaged($e);
        $uow->schedulePersist($e);

        self::assertSame([], $uow->scheduledDeletes());
        self::assertSame([$e], $uow->scheduledUpdates());
    }

    /**
     * Проверяет, что restore() отменяет обычное удаление и планирует восстановление.
     */
    #[Test]
    public function removeThenRestoreCancelsDelete(): void
    {
        $uow = new UnitOfWork();

        $e = new EntityWithNullableIntId(10);

        $uow->markManaged($e);

        $uow->markRemoved($e);
        $uow->scheduleRemove($e);

        self::assertSame([$e], $uow->scheduledDeletes());

        $uow->markManaged($e);
        $uow->scheduleRestore($e);

        self::assertSame([], $uow->scheduledDeletes());
        self::assertSame([], $uow->scheduledForceDeletes());
        self::assertSame([$e], $uow->scheduledRestores());
    }

    /**
     * Проверяет, что restore() отменяет force remove, чтобы одна сущность не ушла в две конфликтующие операции.
     */
    #[Test]
    public function forceRemoveThenRestoreCancelsForceDelete(): void
    {
        $uow = new UnitOfWork();

        $e = new EntityWithNullableIntId(10);

        $uow->markManaged($e);
        $uow->markRemoved($e);
        $uow->scheduleForceRemove($e);

        self::assertSame([$e], $uow->scheduledForceDeletes());

        $uow->markManaged($e);
        $uow->scheduleRestore($e);

        self::assertSame([], $uow->scheduledDeletes());
        self::assertSame([], $uow->scheduledForceDeletes());
        self::assertSame([$e], $uow->scheduledRestores());
    }

    /**
     * Проверяет, что remove() после restore() снова отменяет восстановление.
     */
    #[Test]
    public function restoreThenRemoveCancelsRestore(): void
    {
        $uow = new UnitOfWork();

        $e = new EntityWithNullableIntId(10);

        $uow->markManaged($e);
        $uow->scheduleRestore($e);

        self::assertSame([$e], $uow->scheduledRestores());

        $uow->markRemoved($e);
        $uow->scheduleRemove($e);

        self::assertSame([], $uow->scheduledRestores());
        self::assertSame([$e], $uow->scheduledDeletes());
    }

    /**
     * Проверяет, что повторный persist() не дублирует сущность в scheduledUpdates.
     */
    #[Test]
    public function persistTwiceDoesNotDuplicateUpdate(): void
    {
        $uow = new UnitOfWork();

        $e = new EntityWithNullableIntId(10);

        $uow->markManaged($e);

        $uow->schedulePersist($e);
        $uow->schedulePersist($e);

        self::assertSame([$e], $uow->scheduledUpdates());
    }

    /**
     * Проверяет, что повторный remove() не дублирует сущность в scheduledDeletes.
     */
    #[Test]
    public function removeTwiceDoesNotDuplicateDelete(): void
    {
        $uow = new UnitOfWork();

        $e = new EntityWithNullableIntId(10);

        $uow->markManaged($e);

        $uow->markRemoved($e);
        $uow->scheduleRemove($e);
        $uow->markRemoved($e);
        $uow->scheduleRemove($e);

        self::assertSame([$e], $uow->scheduledDeletes());
    }

    /**
     * Проверяет, что повторный restore() не дублирует сущность в scheduledRestores.
     */
    #[Test]
    public function restoreTwiceDoesNotDuplicateRestore(): void
    {
        $uow = new UnitOfWork();

        $e = new EntityWithNullableIntId(10);

        $uow->markManaged($e);

        $uow->scheduleRestore($e);
        $uow->scheduleRestore($e);

        self::assertSame([$e], $uow->scheduledRestores());
    }
}
