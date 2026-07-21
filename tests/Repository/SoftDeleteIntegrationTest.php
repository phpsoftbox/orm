<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Repository;

use DateTimeImmutable;
use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use PhpSoftBox\Orm\Behavior\Command\AfterRestore;
use PhpSoftBox\Orm\Behavior\Command\OnRestore;
use PhpSoftBox\Orm\Behavior\DefaultEventDispatcher;
use PhpSoftBox\Orm\ChangeLog\EntityChangeAction;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeRecord;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Persistence\DefaultEntityPersister;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Repository\GenericEntityRepository;
use PhpSoftBox\Orm\Tests\Repository\Fixtures\SoftDeleteEntity;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_array;

#[CoversClass(GenericEntityRepository::class)]
#[CoversClass(DefaultEntityPersister::class)]
#[CoversClass(EntityManager::class)]
final class SoftDeleteIntegrationTest extends TestCase
{
    #[Test]
    public function entityManagerFindWithDeletedUsesIdentityMap(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );
        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES (2, 'Deleted', '2026-01-01 00:00:00')
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $managed = $em->queryFor(SoftDeleteEntity::class, withDeleted: true)
            ->where(['id' => 2])
            ->fetchEntity();

        self::assertInstanceOf(SoftDeleteEntity::class, $managed);
        self::assertSame($managed, $em->findWithDeleted(SoftDeleteEntity::class, 2));

        $freshEntityManager = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $loaded = $freshEntityManager->findWithDeleted(SoftDeleteEntity::class, 2);

        self::assertInstanceOf(SoftDeleteEntity::class, $loaded);
        self::assertSame($loaded, $freshEntityManager->findWithDeleted(SoftDeleteEntity::class, 2));
        self::assertSame(
            $loaded,
            $freshEntityManager->unitOfWork()->findManaged(SoftDeleteEntity::class, 2),
        );
    }

    /**
     * Проверяет, что при включенном soft delete:
     * - GenericEntityRepository по умолчанию не возвращает удалённые записи
     * - DefaultEntityPersister::delete() делает UPDATE deleted_datetime вместо физического DELETE
     */
    #[Test]
    public function softDeleteWorks(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $driver = new SqliteDriver();

        $conn = new Connection($pdo, $driver);

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES
                    (1, 'Alive', NULL),
                    (2, 'Deleted', '2026-01-01T00:00:00+00:00')
            ",
        );

        $repo = new GenericEntityRepository($conn, SoftDeleteEntity::class);

        self::assertTrue($repo->exists(1));
        self::assertFalse($repo->exists(2));

        self::assertTrue($repo->existsWithDeleted(1));
        self::assertTrue($repo->existsWithDeleted(2));

        self::assertNotNull($repo->find(1));
        self::assertNull($repo->find(2));

        self::assertNotNull($repo->findWithDeleted(2));

        $all = $repo->all();
        self::assertCount(1, $all->all());

        $allIncluding = $repo->allWithDeleted();
        self::assertCount(2, $allIncluding->all());

        $onlyDeleted = $repo->onlyDeleted();
        self::assertCount(1, $onlyDeleted->all());

        // EntityManager::queryFor должен применять soft delete фильтр по умолчанию
        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $rowsDefault = $em->queryFor(SoftDeleteEntity::class)->fetchAll();
        self::assertCount(1, $rowsDefault);

        $rowsWithDeleted = $em->queryFor(SoftDeleteEntity::class, withDeleted: true)->fetchAll();
        self::assertCount(2, $rowsWithDeleted);

        $deleted = $repo->findWithDeleted(2);
        self::assertInstanceOf(SoftDeleteEntity::class, $deleted);

        $repo->restore($deleted);

        $restoredRow = $conn->fetchOne('SELECT deleted_datetime FROM soft_delete_entities WHERE id = 2');
        self::assertNotNull($restoredRow);
        self::assertNull($restoredRow['deleted_datetime']);
        self::assertNull($deleted->deletedDatetime);
        self::assertNotNull($repo->find(2));

        // delete() должен сделать UPDATE deleted_datetime
        $metadata = new AttributeMetadataProvider();

        $mapper = new AutoEntityMapper(
            metadata: $metadata,
            typeCaster: new DefaultTypeCasterFactory()->create(),
            optionsManager: new TypeCastOptionsManager(),
        );

        $persister = new DefaultEntityPersister(
            connection: $conn,
            metadata: $metadata,
            mapper: $mapper,
        );

        $alive = new SoftDeleteEntity(1, 'Alive');

        $persister->delete($alive);

        $row = $conn->fetchOne('SELECT deleted_datetime FROM soft_delete_entities WHERE id = 1');
        self::assertNotNull($row);
        self::assertNotNull($row['deleted_datetime']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $row['deleted_datetime']);
        self::assertInstanceOf(DateTimeImmutable::class, $alive->deletedDatetime);

        // forceDelete() должен физически удалить запись
        $repo->forceDelete(new SoftDeleteEntity(2, 'Deleted'));
        self::assertNull($conn->fetchOne('SELECT id FROM soft_delete_entities WHERE id = 2'));
    }

    /**
     * Проверяет, что EntityManager::restore() проходит через UnitOfWork, события restore и changelog.
     */
    #[Test]
    public function entityManagerRestoreDispatchesEventsAndWritesChangelog(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES (2, 'Deleted', '2026-01-01 00:00:00')
            ",
        );

        $listener = new class () {
            public bool $onRestoreCalled = false;

            public bool $afterRestoreCalled = false;

            /** @var array<string, mixed> */
            public array $stateAtOnRestore = [];

            /** @var array<string, mixed> */
            public array $stateAtAfterRestore = [];

            #[Listen(OnRestore::class)]
            public function handleRestore(OnRestore $event): void
            {
                $this->onRestoreCalled  = true;
                $this->stateAtOnRestore = $event->state()->getData();

                // SoftDelete-колонка должна оставаться null даже если listener ошибочно её меняет.
                $event->state()->register('deleted_datetime', 'listener_value');
            }

            #[Listen(AfterRestore::class)]
            public function afterRestore(AfterRestore $event): void
            {
                $this->afterRestoreCalled  = true;
                $this->stateAtAfterRestore = $event->state()->getData();

                // После SQL listener тоже не должен испортить restore-changelog.
                $event->state()->register('deleted_datetime', 'after_listener_value');
            }
        };

        $logger = new class () implements EntityChangeLoggerInterface {
            /** @var list<EntityChangeRecord> */
            public array $records = [];

            public function log(EntityChangeRecord $record): void
            {
                $this->records[] = $record;
            }
        };

        $em = new EntityManager(
            connection: $conn,
            unitOfWork: new UnitOfWork(),
            events: new DefaultEventDispatcher([$listener]),
            changeLogger: $logger,
        );

        $entity = new SoftDeleteEntity(
            id: 2,
            name: 'Deleted',
            deletedDatetime: new DateTimeImmutable('2026-01-01 00:00:00'),
        );

        $em->trackHydratedEntities($entity);
        $em->restore($entity);
        $em->flush();

        $row = $conn->fetchOne('SELECT deleted_datetime FROM soft_delete_entities WHERE id = 2');
        self::assertNotNull($row);
        self::assertNull($row['deleted_datetime']);
        self::assertNull($entity->deletedDatetime);

        self::assertTrue($listener->onRestoreCalled);
        self::assertTrue($listener->afterRestoreCalled);
        self::assertNull($listener->stateAtOnRestore['deleted_datetime'] ?? null);
        self::assertNull($listener->stateAtAfterRestore['deleted_datetime'] ?? null);

        self::assertCount(1, $logger->records);

        $record = $logger->records[0];
        self::assertSame(EntityChangeAction::Restore, $record->action);
        self::assertSame(SoftDeleteEntity::class, $record->entityClass);
        self::assertSame(2, $record->entityId);

        $deletedDatetimeChange = $this->findChange($record, 'deleted_datetime');
        self::assertNotNull($deletedDatetimeChange);
        self::assertNotNull($deletedDatetimeChange['before']);
        self::assertNull($deletedDatetimeChange['after']);
    }

    /**
     * Проверяет, что restore() и update одной сущности в одном flush не теряют dirty-поля.
     */
    #[Test]
    public function restoreAndUpdateInSameFlushKeepsDirtyFields(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES (2, 'Deleted', '2026-01-01 00:00:00')
            ",
        );

        $logger = new class () implements EntityChangeLoggerInterface {
            /** @var list<EntityChangeRecord> */
            public array $records = [];

            public function log(EntityChangeRecord $record): void
            {
                $this->records[] = $record;
            }
        };

        $em = new EntityManager(
            connection: $conn,
            unitOfWork: new UnitOfWork(),
            changeLogger: $logger,
        );

        $em->registerRepository(SoftDeleteEntity::class, new GenericEntityRepository($conn, SoftDeleteEntity::class));

        $entity = new SoftDeleteEntity(
            id: 2,
            name: 'Deleted',
            deletedDatetime: new DateTimeImmutable('2026-01-01 00:00:00'),
        );

        $em->trackHydratedEntities($entity);

        $entity->name = 'Restored';

        $em->restore($entity);
        $em->persist($entity);
        $em->flush();

        $row = $conn->fetchOne('SELECT name, deleted_datetime FROM soft_delete_entities WHERE id = 2');
        self::assertNotNull($row);
        self::assertSame('Restored', $row['name']);
        self::assertNull($row['deleted_datetime']);

        self::assertCount(2, $logger->records);
        self::assertSame(EntityChangeAction::Restore, $logger->records[0]->action);
        self::assertSame(EntityChangeAction::Update, $logger->records[1]->action);

        $nameChange = $this->findChange($logger->records[1], 'name');
        self::assertNotNull($nameChange);
        self::assertSame('Deleted', $nameChange['before']);
        self::assertSame('Restored', $nameChange['after']);
        self::assertNull($this->findChange($logger->records[1], 'deleted_datetime'));
    }

    /**
     * @return array{field: string, before: mixed, after: mixed}|null
     */
    private function findChange(EntityChangeRecord $record, string $field): ?array
    {
        foreach ($record->changes as $change) {
            if (!is_array($change)) {
                continue;
            }

            if (($change['field'] ?? null) !== $field) {
                continue;
            }

            return $change;
        }

        return null;
    }
}
