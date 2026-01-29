<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Repository;

use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\Repository\Fixtures\SoftDeleteUser;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class ConventionRepositoryReadBehaviorsTest extends TestCase
{
    /**
     * Проверяет, что при создании репозитория по соглашению (EntityManager->repository())
     * он получает ссылку на EntityManager и использует queryFor(),
     * поэтому read-behaviors (soft delete) применяются автоматически.
     */
    #[Test]
    public function conventionRepositoryUsesReadBehaviors(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            "
                CREATE TABLE sd_users (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            "
        );

        $conn->execute(
            "
                INSERT INTO sd_users (id, name, deleted_datetime)
                VALUES
                    (1, 'Alive', NULL),
                    (2, 'Deleted', '2026-01-01T00:00:00+00:00')
            "
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        // Важно: репозиторий НЕ регистрируем вручную. Он должен резолвиться по соглашению:
        // SoftDeleteUser::class -> PhpSoftBox\Orm\Tests\Repository\Fixtures\Repository\SoftDeleteUserRepository
        $repo = $em->repository(SoftDeleteUser::class);

        // контракт репозитория нам важнее конкретного класса: проверяем поведение.
        $onlyAlive = $repo->all();
        self::assertCount(1, $onlyAlive->all());

        $foundAlive = $repo->find(1);
        self::assertNotNull($foundAlive);

        $foundDeleted = $repo->find(2);
        self::assertNull($foundDeleted);
    }
}

