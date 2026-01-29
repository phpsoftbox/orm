<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\UserWithProfile;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class HasOneIntegrationTest extends TestCase
{
    /**
     * Проверяет hasOne eager loading: repo->with(['profile'])->all()
     * должен заполнить свойство profile.
     */
    #[Test]
    public function eagerLoadsHasOne(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE users_profile (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                CREATE TABLE profiles (
                    id INTEGER PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    bio VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO users_profile (id, name) VALUES (1, 'Anton')
            ",
        );

        $conn->execute(
            "
                INSERT INTO profiles (id, user_id, bio) VALUES (10, 1, 'dev')
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        $repo = $em->repository(UserWithProfile::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        /** @var AbstractEntityRepository<UserWithProfile> $repo */
        $users = $repo->with(['profile'])->all();

        $items = $users->all();
        self::assertCount(1, $items);
        self::assertNotNull($items[0]->profile);
        self::assertSame('dev', $items[0]->profile->bio);
    }
}
