<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\GenericEntityRepository;
use PhpSoftBox\Orm\Tests\Repository\Fixtures\SoftDeleteEntity;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class EntityManagerForceRemoveIntegrationTest extends TestCase
{
    /**
     * Проверяет, что forceRemove() приводит к физическому удалению записи из БД,
     * даже если на сущности включён soft delete.
     */
    #[Test]
    public function forceRemovePhysicallyDeletesRow(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

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
                VALUES (1, 'Alive', NULL)
            "
        );

        $em = new EntityManager(
            connection: $conn,
            unitOfWork: new InMemoryUnitOfWork(),
        );

        // чтобы persist/remove/forceRemove не требовали auto-resolve репозитория
        $em->registerRepository(SoftDeleteEntity::class, new GenericEntityRepository($conn, SoftDeleteEntity::class));

        $em->forceRemove(new SoftDeleteEntity(1, 'Alive'));
        $em->flush();

        self::assertNull($conn->fetchOne('SELECT id FROM soft_delete_entities WHERE id = 1'));
    }
}

