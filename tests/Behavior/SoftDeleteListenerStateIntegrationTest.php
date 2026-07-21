<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\Behavior\DefaultEventDispatcher;
use PhpSoftBox\Orm\ChangeLog\EntityChangeAction;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeRecord;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Persistence\DefaultEntityPersister;
use PhpSoftBox\Orm\Repository\GenericEntityRepository;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\DeleteStateListener;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\HardDeleteStatusEntity;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\SoftDeleteStatusEntity;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
#[CoversClass(DefaultEntityPersister::class)]
#[CoversMethod(EntityManager::class, 'flush')]
#[CoversMethod(DefaultEntityPersister::class, 'delete')]
final class SoftDeleteListenerStateIntegrationTest extends TestCase
{
    /**
     * Проверим, что колонки, добавленные слушателем OnDelete, уходят в тот же UPDATE, что и колонка soft delete.
     *
     * @see EntityManager::flush()
     * @see DefaultEntityPersister::delete()
     */
    #[Test]
    public function listenerColumnsGoIntoSoftDeleteUpdate(): void
    {
        $connection = $this->softDeleteConnection();
        $em         = $this->entityManager($connection, new DeleteStateListener(['status' => 'archived']));

        $em->remove($this->managedSoftDeleteEntity($em));
        $em->flush();

        $row = $connection->fetchOne('SELECT name, status, deleted_datetime FROM soft_delete_status_entities');

        self::assertNotNull($row);
        self::assertSame('archived', $row['status']);
        self::assertNotNull($row['deleted_datetime']);
        // Колонки, которых слушатель не касался, остаются прежними.
        self::assertSame('Post', $row['name']);
    }

    /**
     * Проверим, что без слушателя soft delete меняет только свою колонку.
     *
     * @see EntityManager::flush()
     * @see DefaultEntityPersister::delete()
     */
    #[Test]
    public function softDeleteWithoutListenerChangesOnlyItsOwnColumn(): void
    {
        $connection = $this->softDeleteConnection();
        $em         = $this->entityManager($connection);

        $em->remove($this->managedSoftDeleteEntity($em));
        $em->flush();

        $row = $connection->fetchOne('SELECT status, deleted_datetime FROM soft_delete_status_entities');

        self::assertNotNull($row);
        self::assertSame('active', $row['status']);
        self::assertNotNull($row['deleted_datetime']);
    }

    /**
     * Проверим, что колонка, которой нет в метаданных сущности, игнорируется и не ломает запрос.
     *
     * @see DefaultEntityPersister::delete()
     */
    #[Test]
    public function unknownColumnsAreIgnored(): void
    {
        $connection = $this->softDeleteConnection();
        $em         = $this->entityManager($connection, new DeleteStateListener(['not_a_column' => 'x']));

        $em->remove($this->managedSoftDeleteEntity($em));
        $em->flush();

        $row = $connection->fetchOne('SELECT deleted_datetime FROM soft_delete_status_entities');

        self::assertNotNull($row);
        self::assertNotNull($row['deleted_datetime']);
    }

    /**
     * Проверим, что при физическом удалении колонки из состояния игнорируются: обновлять нечего.
     *
     * @see EntityManager::flush()
     * @see DefaultEntityPersister::delete()
     */
    #[Test]
    public function hardDeleteIgnoresListenerColumns(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $connection = new Connection($pdo, new SqliteDriver());

        $connection->execute('
            CREATE TABLE hard_delete_status_entities (
                id INTEGER PRIMARY KEY,
                status VARCHAR(32) NOT NULL
            )
        ');
        $connection->execute("
            INSERT INTO hard_delete_status_entities (id, status) VALUES (1, 'active')
        ");

        $em = new EntityManager(
            connection: $connection,
            unitOfWork: new UnitOfWork(),
            events: new DefaultEventDispatcher([new DeleteStateListener(['status' => 'archived'])]),
        );

        $em->registerRepository(
            HardDeleteStatusEntity::class,
            new GenericEntityRepository($connection, HardDeleteStatusEntity::class),
        );

        $entity = $em->find(HardDeleteStatusEntity::class, 1);
        self::assertInstanceOf(HardDeleteStatusEntity::class, $entity);

        $em->remove($entity);
        $em->flush();

        self::assertNull($connection->fetchOne('SELECT id FROM hard_delete_status_entities'));
    }

    /**
     * Проверим, что добавленные слушателем колонки попадают в changelog как новое состояние записи.
     *
     * @see EntityManager::flush()
     */
    #[Test]
    public function changeLogRecordContainsListenerColumns(): void
    {
        $connection = $this->softDeleteConnection();

        $logger = new class () implements EntityChangeLoggerInterface {
            /** @var list<EntityChangeRecord> */
            public array $records = [];

            public function log(EntityChangeRecord $record): void
            {
                $this->records[] = $record;
            }
        };

        $em = $this->entityManager($connection, new DeleteStateListener(['status' => 'archived']), $logger);

        $em->remove($this->managedSoftDeleteEntity($em));
        $em->flush();

        self::assertCount(1, $logger->records);
        self::assertSame(EntityChangeAction::Delete, $logger->records[0]->action);
        self::assertSame(['status' => 'archived'], $logger->records[0]->after);
    }

    private function softDeleteConnection(): ConnectionInterface
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $connection = new Connection($pdo, new SqliteDriver());

        $connection->execute('
            CREATE TABLE soft_delete_status_entities (
                id INTEGER PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                status VARCHAR(32) NOT NULL,
                deleted_datetime VARCHAR(64) NULL
            )
        ');
        $connection->execute("
            INSERT INTO soft_delete_status_entities (id, name, status) VALUES (1, 'Post', 'active')
        ");

        return $connection;
    }

    private function entityManager(
        ConnectionInterface $connection,
        ?DeleteStateListener $listener = null,
        ?EntityChangeLoggerInterface $changeLogger = null,
    ): EntityManager {
        $em = new EntityManager(
            connection: $connection,
            unitOfWork: new UnitOfWork(),
            events: new DefaultEventDispatcher($listener !== null ? [$listener] : []),
            changeLogger: $changeLogger,
        );

        $em->registerRepository(
            SoftDeleteStatusEntity::class,
            new GenericEntityRepository($connection, SoftDeleteStatusEntity::class),
        );

        return $em;
    }

    private function managedSoftDeleteEntity(EntityManager $em): SoftDeleteStatusEntity
    {
        $entity = $em->find(SoftDeleteStatusEntity::class, 1);
        self::assertInstanceOf(SoftDeleteStatusEntity::class, $entity);

        return $entity;
    }
}
