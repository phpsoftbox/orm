<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Repository;

use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Persistence\DefaultEntityPersister;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Repository\GenericEntityRepository;
use PhpSoftBox\Orm\Tests\Repository\Fixtures\SoftDeleteEntity;
use PhpSoftBox\Orm\TypeCasting\DefaultTypeCasterFactory;
use PhpSoftBox\Orm\TypeCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenericEntityRepository::class)]
#[CoversClass(DefaultEntityPersister::class)]
final class SoftDeleteIntegrationTest extends TestCase
{
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
            "
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            "
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES
                    (1, 'Alive', NULL),
                    (2, 'Deleted', '2026-01-01T00:00:00+00:00')
            "
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
        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        $rowsDefault = $em->queryFor(SoftDeleteEntity::class)->fetchAll();
        self::assertCount(1, $rowsDefault);

        $rowsWithDeleted = $em->queryFor(SoftDeleteEntity::class, withDeleted: true)->fetchAll();
        self::assertCount(2, $rowsWithDeleted);

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

        $persister->delete(new SoftDeleteEntity(1, 'Alive'));

        $row = $conn->fetchOne('SELECT deleted_datetime FROM soft_delete_entities WHERE id = 1');
        self::assertNotNull($row);
        self::assertNotNull($row['deleted_datetime']);

        // forceDelete() должен физически удалить запись
        $repo->forceDelete(new SoftDeleteEntity(2, 'Deleted'));
        self::assertNull($conn->fetchOne('SELECT id FROM soft_delete_entities WHERE id = 2'));
    }
}
