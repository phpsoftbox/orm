<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Bulk;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Database\Exception\QueryException;
use PhpSoftBox\Orm\Bulk\EntityBulkWriter;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\Bulk\Fixtures\BulkCastEntity;
use PhpSoftBox\Orm\Tests\Bulk\Fixtures\BulkListenedEntity;
use PhpSoftBox\Orm\Tests\Bulk\Fixtures\BulkRecordingListener;
use PhpSoftBox\Orm\Tests\Bulk\Fixtures\BulkStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_column;

#[CoversClass(EntityBulkWriter::class)]
#[CoversMethod(EntityBulkWriter::class, 'update')]
final class EntityBulkWriterCastingTest extends TestCase
{
    /**
     * Проверим, что bulk update приводит значения через DataCasting по метаданным колонок
     * (enum → value, bool → 0/1, массив → JSON), как при записи сущности.
     *
     * @see EntityBulkWriter::update()
     */
    #[Test]
    public function updateCastsValuesByColumnMetadata(): void
    {
        $conn = $this->connection();
        $em   = new EntityManager(connection: $conn);

        $em->bulk(BulkCastEntity::class)->ids([1, 2])->update([
            'status'  => BulkStatus::Archived,
            'active'  => false,
            'payload' => ['reason' => 'cleanup'],
        ]);

        $rows = $conn->fetchAll('SELECT status, is_active, payload FROM bulk_cast_entities WHERE id IN (1, 2) ORDER BY id');

        foreach ($rows as $row) {
            self::assertSame('archived', $row['status']);
            self::assertSame(0, (int) $row['is_active']);
            self::assertSame('{"reason":"cleanup"}', $row['payload']);
        }
    }

    /**
     * Проверим, что ошибка на одном из чанков откатывает уже применённые чанки.
     *
     * @see EntityBulkWriter::update()
     */
    #[Test]
    public function failedChunkRollsBackAppliedChunks(): void
    {
        $conn = $this->connection();

        // Обновление строки id=3 (третий чанк) падает.
        $conn->execute(
            '
                CREATE TRIGGER bulk_cast_entities_fail BEFORE UPDATE ON bulk_cast_entities
                WHEN NEW.id = 3
                BEGIN
                    SELECT RAISE(ABORT, \'chunk failed\');
                END
            ',
        );

        $em = new EntityManager(connection: $conn);

        try {
            $em->bulk(BulkCastEntity::class)->ids([1, 2, 3])->chunkSize(1)->update(['name' => 'Updated']);
            self::fail('Ошибка третьего чанка должна быть проброшена.');
        } catch (QueryException) {
            // ожидаемо
        }

        $names = $conn->fetchAll('SELECT name FROM bulk_cast_entities ORDER BY id');
        self::assertSame(['one', 'two', 'three'], array_column($names, 'name'));
    }

    /**
     * Проверим, что bulk update выполняется внутри внешней транзакции: её откат отменяет и bulk-изменения.
     *
     * @see EntityBulkWriter::update()
     */
    #[Test]
    public function outerTransactionRollbackRevertsBulkUpdate(): void
    {
        $conn = $this->connection();
        $em   = new EntityManager(connection: $conn);

        try {
            $conn->transaction(static function () use ($em): void {
                $em->bulk(BulkCastEntity::class)->ids([1, 2])->update(['name' => 'Updated']);

                throw new RuntimeException('outer failure');
            });
        } catch (RuntimeException) {
            // ожидаемо
        }

        $names = $conn->fetchAll('SELECT name FROM bulk_cast_entities ORDER BY id');
        self::assertSame(['one', 'two', 'three'], array_column($names, 'name'));
    }

    /**
     * Проверим, что #[EventListener] сущности получает bulk-события своей сущности и не получает чужие.
     *
     * @see EntityBulkWriter::update()
     */
    #[Test]
    public function entityListenerReceivesBulkEventsOfItsEntity(): void
    {
        BulkRecordingListener::$entities = [];

        $em = new EntityManager(connection: $this->connection());

        $em->bulk(BulkCastEntity::class)->ids([1])->update(['name' => 'Updated']);
        $em->bulk(BulkListenedEntity::class)->ids([2])->update(['name' => 'Updated']);

        self::assertSame([BulkListenedEntity::class], BulkRecordingListener::$entities);
    }

    private function connection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE bulk_cast_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    status VARCHAR(32) NOT NULL,
                    is_active INTEGER NOT NULL,
                    payload TEXT NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                INSERT INTO bulk_cast_entities (id, name, status, is_active, payload)
                VALUES
                    (1, \'one\', \'active\', 1, \'{}\'),
                    (2, \'two\', \'active\', 1, \'{}\'),
                    (3, \'three\', \'active\', 1, \'{}\')
            ',
        );

        return $conn;
    }
}
