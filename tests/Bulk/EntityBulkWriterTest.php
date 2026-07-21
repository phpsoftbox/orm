<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Bulk;

use DateTimeImmutable;
use LogicException;
use PDO;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use PhpSoftBox\Orm\Behavior\DefaultEventDispatcher;
use PhpSoftBox\Orm\Bulk\AfterBulkRemove;
use PhpSoftBox\Orm\Bulk\BulkWriteAction;
use PhpSoftBox\Orm\Bulk\EntityBulkWriter;
use PhpSoftBox\Orm\Bulk\OnBulkRemove;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\Bulk\Fixtures\BulkWriteEntity;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityBulkWriter::class)]
final class EntityBulkWriterTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::reset();
    }

    /**
     * Проверяет, что bulk remove для SoftDelete выполняет один set-based UPDATE и диспатчит bulk-события.
     */
    #[Test]
    public function removeSoftDeletesRowsAndDispatchesBulkEvents(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-02-01 10:00:00'));

        $conn = $this->connectionWithRows();

        $listener = new class () {
            public bool $onCalled = false;

            public bool $afterCalled = false;

            public int $affectedRows = 0;

            #[Listen(OnBulkRemove::class)]
            public function before(OnBulkRemove $event): void
            {
                $this->onCalled = true;

                $event->state()->register('deleted_datetime', '2026-02-01 11:00:00');
            }

            #[Listen(AfterBulkRemove::class)]
            public function after(AfterBulkRemove $event): void
            {
                $this->afterCalled  = true;
                $this->affectedRows = $event->result()->affectedRows;
            }
        };

        $em = new EntityManager(
            connection: $conn,
            unitOfWork: new UnitOfWork(),
            events: new DefaultEventDispatcher([$listener]),
        );

        $result = $em->bulk(BulkWriteEntity::class)->ids([1, 2, 2, 3])->remove();

        self::assertSame(BulkWriteAction::Remove, $result->action);
        self::assertSame(3, $result->requestedValues);
        self::assertSame(3, $result->affectedRows);
        self::assertSame([1, 2, 3], $result->lookupValues);
        self::assertTrue($listener->onCalled);
        self::assertTrue($listener->afterCalled);
        self::assertSame(3, $listener->affectedRows);

        $rows = $conn->fetchAll('SELECT id, deleted_datetime FROM bulk_write_entities ORDER BY id');
        self::assertSame('2026-02-01 11:00:00', $rows[0]['deleted_datetime']);
        self::assertSame('2026-02-01 11:00:00', $rows[1]['deleted_datetime']);
        self::assertSame('2026-02-01 11:00:00', $rows[2]['deleted_datetime']);
    }

    /**
     * Проверяет, что forceRemove физически удаляет строки, даже если сущность поддерживает SoftDelete.
     */
    #[Test]
    public function forceRemoveDeletesRowsPhysically(): void
    {
        $conn = $this->connectionWithRows();
        $em   = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $result = $em->bulk(BulkWriteEntity::class)->ids([1, 3])->forceRemove();

        self::assertSame(BulkWriteAction::ForceRemove, $result->action);
        self::assertSame(2, $result->affectedRows);
        self::assertSame(1, (int) $conn->fetchOne('SELECT COUNT(*) AS c FROM bulk_write_entities')['c']);
        self::assertNotNull($conn->fetchOne('SELECT id FROM bulk_write_entities WHERE id = 2'));
    }

    /**
     * Проверяет, что bulk restore очищает soft-delete колонку.
     */
    #[Test]
    public function restoreClearsSoftDeleteColumn(): void
    {
        $conn = $this->connectionWithRows();
        $conn->execute("UPDATE bulk_write_entities SET deleted_datetime = '2026-01-01 00:00:00' WHERE id IN (1, 2)");

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $result = $em->bulk(BulkWriteEntity::class)->ids([1, 2])->restore();

        self::assertSame(BulkWriteAction::Restore, $result->action);
        self::assertSame(2, $result->affectedRows);

        $rows = $conn->fetchAll('SELECT deleted_datetime FROM bulk_write_entities WHERE id IN (1, 2) ORDER BY id');
        self::assertNull($rows[0]['deleted_datetime']);
        self::assertNull($rows[1]['deleted_datetime']);
    }

    /**
     * Проверяет, что bulk update принимает имена свойств и автоматически ставит updated_datetime.
     */
    #[Test]
    public function updateUsesPropertyNamesAndUpdatesTimestamp(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-02-01 12:00:00'));

        $conn = $this->connectionWithRows();
        $em   = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $lookup = LookupSpec::forTable('bulk_write_entities')
            ->lookupColumn('id')
            ->values([1, 2, 3])
            ->where('name', 'Alpha');

        $result = $em->bulk(BulkWriteEntity::class)
            ->lookup($lookup)
            ->update(['name' => 'Updated']);

        self::assertSame(BulkWriteAction::Update, $result->action);
        self::assertSame(2, $result->affectedRows);

        $rows = $conn->fetchAll('SELECT id, name, updated_datetime FROM bulk_write_entities ORDER BY id');
        self::assertSame('Updated', $rows[0]['name']);
        self::assertSame('Updated', $rows[1]['name']);
        self::assertSame('Beta', $rows[2]['name']);
        self::assertSame('2026-02-01T12:00:00+00:00', $rows[0]['updated_datetime']);
        self::assertSame('2026-02-01T12:00:00+00:00', $rows[1]['updated_datetime']);
        self::assertNull($rows[2]['updated_datetime']);
    }

    /**
     * Проверяет, что set-based bulk write нельзя смешивать с незавершенным UnitOfWork.
     */
    #[Test]
    public function bulkWriteFailsWhenUnitOfWorkHasScheduledOperations(): void
    {
        $conn = $this->connectionWithRows();
        $em   = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $em->persist(new BulkWriteEntity(10, 'Pending'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Bulk write cannot run while UnitOfWork has scheduled operations.');

        $em->bulk(BulkWriteEntity::class)->ids([1])->remove();
    }

    /**
     * Проверяет, что после bulk write UnitOfWork очищается, чтобы managed-сущности не остались stale.
     */
    #[Test]
    public function bulkWriteClearsUnitOfWorkAfterExecution(): void
    {
        $conn = $this->connectionWithRows();
        $uow  = new UnitOfWork();

        $em = new EntityManager(connection: $conn, unitOfWork: $uow);

        $entity = new BulkWriteEntity(1, 'Alpha');

        $em->trackHydratedEntities($entity);

        self::assertNotNull($uow->state($entity));

        $em->bulk(BulkWriteEntity::class)->ids([1])->update(['name' => 'Updated']);

        self::assertNull($uow->state($entity));
    }

    private function connectionWithRows(): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE bulk_write_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL,
                    updated_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO bulk_write_entities (id, name, deleted_datetime, updated_datetime)
                VALUES
                    (1, 'Alpha', NULL, NULL),
                    (2, 'Alpha', NULL, NULL),
                    (3, 'Beta', NULL, NULL)
            ",
        );

        return $conn;
    }
}
