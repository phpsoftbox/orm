<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior;

use DateTimeImmutable;
use PDO;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\EntityManagerConfig;
use PhpSoftBox\Orm\Exception\EntityPersistException;
use PhpSoftBox\Orm\Exception\UninitializedMappedPropertyException;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\ListenerTimestampEntity;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\UninitializedRequiredEntity;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class TimestampsLifecycleIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::reset();
    }

    /**
     * Проверяет: nullable transient timestamps заполняются listener-ом,
     * а metadata и DB-колонки при этом остаются NOT NULL.
     */
    #[Test]
    public function listenerFillsTransientTimestampsOnInsertAndUpdate(): void
    {
        $connection = $this->connection();
        $this->createTimestampTable($connection);

        $orm    = new EntityManager($connection, new UnitOfWork());
        $entity = new ListenerTimestampEntity('Initial');

        self::assertNull($entity->createdDatetime);
        self::assertNull($entity->updatedDatetime);

        Clock::freeze(new DateTimeImmutable('2026-08-28 10:00:00'));

        $orm->persist($entity);
        $orm->flush();

        self::assertInstanceOf(DatePoint::class, $entity->createdDatetime);
        self::assertInstanceOf(DatePoint::class, $entity->updatedDatetime);
        self::assertSame('2026-08-28 10:00:00', $entity->createdDatetime->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-28 10:00:00', $entity->updatedDatetime->format('Y-m-d H:i:s'));

        $inserted = $connection->fetchOne(
            'SELECT created_datetime, updated_datetime FROM listener_timestamp_entities WHERE id = :id',
            ['id' => $entity->id],
        );
        self::assertNotNull($inserted);
        self::assertSame('2026-08-28 10:00:00', new DateTimeImmutable((string) $inserted['created_datetime'])->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-28 10:00:00', new DateTimeImmutable((string) $inserted['updated_datetime'])->format('Y-m-d H:i:s'));

        Clock::freeze(new DateTimeImmutable('2026-08-28 11:30:00'));
        $entity->name = 'Updated';

        $orm->persist($entity);
        $orm->flush();

        self::assertSame('2026-08-28 10:00:00', $entity->createdDatetime->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-28 11:30:00', $entity->updatedDatetime->format('Y-m-d H:i:s'));

        $updated = $connection->fetchOne(
            'SELECT created_datetime, updated_datetime FROM listener_timestamp_entities WHERE id = :id',
            ['id' => $entity->id],
        );
        self::assertNotNull($updated);
        self::assertSame('2026-08-28 10:00:00', new DateTimeImmutable((string) $updated['created_datetime'])->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-28 11:30:00', new DateTimeImmutable((string) $updated['updated_datetime'])->format('Y-m-d H:i:s'));
    }

    /**
     * Проверяет: lifecycle guard останавливает INSERT до SQL, если обязательные
     * listener-managed значения остались null.
     */
    #[Test]
    public function missingListenerValueFailsBeforeInsert(): void
    {
        $connection = $this->connection();
        $this->createTimestampTable($connection);

        $orm = new EntityManager(
            connection: $connection,
            unitOfWork: new UnitOfWork(),
            config: new EntityManagerConfig(enableBuiltInListeners: false),
        );

        $orm->persist(new ListenerTimestampEntity('No listeners'));

        $this->expectException(EntityPersistException::class);
        $this->expectExceptionMessage(
            'required mapped property $createdDatetime (column "created_datetime") is null or missing after lifecycle listeners',
        );

        $orm->flush();
    }

    /**
     * Проверяет: mapper не скрывает произвольное uninitialized business property.
     */
    #[Test]
    public function uninitializedRequiredPropertyHasTypedDiagnostic(): void
    {
        $connection = $this->connection();
        $orm        = new EntityManager($connection, new UnitOfWork());

        $orm->persist(new UninitializedRequiredEntity());

        $this->expectException(UninitializedMappedPropertyException::class);
        $this->expectExceptionMessage(
            'Mapped property ' . UninitializedRequiredEntity::class . '::$name is not initialized',
        );

        $orm->flush();
    }

    private function connection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new Connection($pdo, new SqliteDriver());
    }

    private function createTimestampTable(Connection $connection): void
    {
        $connection->execute(
            '
                CREATE TABLE listener_timestamp_entities (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    created_datetime DATETIME NOT NULL,
                    updated_datetime DATETIME NOT NULL
                )
            ',
        );
    }
}
