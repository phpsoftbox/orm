<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\FixturesPivot\Repository\RoleRepository;
use PhpSoftBox\Orm\Tests\Relations\FixturesPivot\Repository\UserRepository;
use PhpSoftBox\Orm\Tests\Relations\FixturesPivot\Role;
use PhpSoftBox\Orm\Tests\Relations\FixturesPivot\User;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(EntityManager::class)]
final class PivotRelationWriterIntegrationTest extends TestCase
{
    /**
     * Проверяет, что attach() добавляет строку в pivot-таблицу.
     */
    #[Test]
    public function attachInsertsPivotRow(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE users_pivot_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                CREATE TABLE roles_pivot_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                CREATE TABLE user_role_pivot_rel (
                    user_id INTEGER NOT NULL,
                    role_id INTEGER NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO users_pivot_rel (id, name) VALUES (1, 'Anton')
            ",
        );

        $conn->execute(
            "
                INSERT INTO roles_pivot_rel (id, name) VALUES (10, 'admin')
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        $em->registerRepository(User::class, new UserRepository($conn, $em));
        $em->registerRepository(Role::class, new RoleRepository($conn, $em));

        $repo = $em->repository(User::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        /** @var AbstractEntityRepository<User> $repo */
        $user = $repo->find(1);
        self::assertNotNull($user);

        $em->pivot($user, 'roles')->attach(10);

        $row = $conn->fetchOne(
            '
                SELECT user_id, role_id FROM user_role_pivot_rel WHERE user_id = 1 AND role_id = 10
            ',
        );

        self::assertNotNull($row);
    }

    /**
     * Проверяет, что detach() удаляет строку из pivot-таблицы.
     */
    #[Test]
    public function detachDeletesPivotRow(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE users_pivot_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                CREATE TABLE roles_pivot_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                CREATE TABLE user_role_pivot_rel (
                    user_id INTEGER NOT NULL,
                    role_id INTEGER NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO users_pivot_rel (id, name) VALUES (1, 'Anton')
            ",
        );

        $conn->execute(
            "
                INSERT INTO roles_pivot_rel (id, name) VALUES (10, 'admin')
            ",
        );

        $conn->execute(
            '
                INSERT INTO user_role_pivot_rel (user_id, role_id) VALUES (1, 10)
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        $em->registerRepository(User::class, new UserRepository($conn, $em));
        $em->registerRepository(Role::class, new RoleRepository($conn, $em));

        /** @var AbstractEntityRepository<User> $repo */
        $repo = $em->repository(User::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        $user = $repo->find(1);
        self::assertNotNull($user);

        $em->pivot($user, 'roles')->detach(10);

        $row = $conn->fetchOne(
            '
                SELECT user_id, role_id FROM user_role_pivot_rel WHERE user_id = 1 AND role_id = 10
            ',
        );

        self::assertNull($row);
    }

    /**
     * Проверяет, что sync() приводит pivot-таблицу к точному набору связей.
     */
    #[Test]
    public function syncMakesPivotMatchExactIds(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE users_pivot_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                CREATE TABLE roles_pivot_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                CREATE TABLE user_role_pivot_rel (
                    user_id INTEGER NOT NULL,
                    role_id INTEGER NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO users_pivot_rel (id, name) VALUES (1, 'Anton')
            ",
        );

        $conn->execute(
            "
                INSERT INTO roles_pivot_rel (id, name) VALUES (10, 'admin')
            ",
        );

        $conn->execute(
            "
                INSERT INTO roles_pivot_rel (id, name) VALUES (11, 'editor')
            ",
        );

        $conn->execute(
            "
                INSERT INTO roles_pivot_rel (id, name) VALUES (12, 'viewer')
            ",
        );

        // Изначально: (1,10) и (1,11)
        $conn->execute(
            '
                INSERT INTO user_role_pivot_rel (user_id, role_id) VALUES (1, 10)
            ',
        );

        $conn->execute(
            '
                INSERT INTO user_role_pivot_rel (user_id, role_id) VALUES (1, 11)
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        $em->registerRepository(User::class, new UserRepository($conn, $em));
        $em->registerRepository(Role::class, new RoleRepository($conn, $em));

        /** @var AbstractEntityRepository<User> $repo */
        $repo = $em->repository(User::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        $user = $repo->find(1);
        self::assertNotNull($user);

        // Приводим к [11,12]
        $em->pivot($user, 'roles')->sync([11, 12]);

        $rows = $conn->fetchAll(
            '
                SELECT role_id FROM user_role_pivot_rel WHERE user_id = 1 ORDER BY role_id
            ',
        );

        $roleIds = array_map(static fn (array $row): int => (int) $row['role_id'], $rows);
        self::assertSame([11, 12], $roleIds);
    }

    /**
     * Проверяет, что syncWithPivotData() вставляет pivotData при attach,
     * а при updatePivot=true обновляет pivotData для существующих связей.
     */
    #[Test]
    public function syncWithPivotDataInsertsAndOptionallyUpdatesPivotFields(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE users_pivot_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                CREATE TABLE roles_pivot_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                CREATE TABLE user_role_pivot_rel (
                    user_id INTEGER NOT NULL,
                    role_id INTEGER NOT NULL,
                    created_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO users_pivot_rel (id, name) VALUES (1, 'Anton')
            ",
        );

        $conn->execute(
            "
                INSERT INTO roles_pivot_rel (id, name) VALUES (10, 'admin')
            ",
        );

        $conn->execute(
            "
                INSERT INTO roles_pivot_rel (id, name) VALUES (11, 'editor')
            ",
        );

        // Уже есть связь (1,10), но created_datetime = NULL
        $conn->execute(
            '
                INSERT INTO user_role_pivot_rel (user_id, role_id, created_datetime)
                VALUES (1, 10, NULL)
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        $em->registerRepository(User::class, new UserRepository($conn, $em));
        $em->registerRepository(Role::class, new RoleRepository($conn, $em));

        /** @var AbstractEntityRepository<User> $repo */
        $repo = $em->repository(User::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        $user = $repo->find(1);
        self::assertNotNull($user);

        // 1) Без updatePivot: (1,10) не должен обновиться, (1,11) должен вставиться с pivotData.
        $em->pivot($user, 'roles')->syncWithPivotData([
            10 => ['created_datetime' => '2026-01-27T12:00:00+00:00'],
            11 => ['created_datetime' => '2026-01-27T12:05:00+00:00'],
        ], updatePivot: false);

        $row10 = $conn->fetchOne(
            '
                SELECT created_datetime FROM user_role_pivot_rel WHERE user_id = 1 AND role_id = 10
            ',
        );
        self::assertNotNull($row10);
        self::assertNull($row10['created_datetime']);

        $row11 = $conn->fetchOne(
            '
                SELECT created_datetime FROM user_role_pivot_rel WHERE user_id = 1 AND role_id = 11
            ',
        );
        self::assertNotNull($row11);
        self::assertSame('2026-01-27T12:05:00+00:00', $row11['created_datetime']);

        // 2) С updatePivot: существующий (1,10) должен обновиться.
        $em->pivot($user, 'roles')->syncWithPivotData([
            10 => ['created_datetime' => '2026-01-27T13:00:00+00:00'],
            11 => ['created_datetime' => '2026-01-27T12:05:00+00:00'],
        ], updatePivot: true);

        $row10b = $conn->fetchOne(
            '
                SELECT created_datetime FROM user_role_pivot_rel WHERE user_id = 1 AND role_id = 10
            ',
        );
        self::assertNotNull($row10b);
        self::assertSame('2026-01-27T13:00:00+00:00', $row10b['created_datetime']);
    }
}
