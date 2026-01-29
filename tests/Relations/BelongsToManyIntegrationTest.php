<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\RoleRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\UserWithRolesRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Role;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\UserWithRoles;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(EntityManager::class)]
final class BelongsToManyIntegrationTest extends TestCase
{
    /**
     * Проверяет eager loading связи belongsToMany через repo->with(['roles'])->all().
     */
    #[Test]
    public function eagerLoadsBelongsToMany(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE users_roles_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE roles_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE user_role_rel (
                    user_id INTEGER NOT NULL,
                    role_id INTEGER NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO users_roles_rel (id, name) VALUES (1, 'Anton')
            ",
        );
        $conn->execute(
            "
                INSERT INTO roles_rel (id, name) VALUES (10, 'admin')
            ",
        );
        $conn->execute(
            "
                INSERT INTO roles_rel (id, name) VALUES (11, 'user')
            ",
        );
        $conn->execute(
            '
                INSERT INTO user_role_rel (user_id, role_id) VALUES (1, 10)
            ',
        );
        $conn->execute(
            '
                INSERT INTO user_role_rel (user_id, role_id) VALUES (1, 11)
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        // регистрируем явные репозитории для теста
        $em->registerRepository(UserWithRoles::class, new UserWithRolesRepository($conn, $em));
        $em->registerRepository(Role::class, new RoleRepository($conn, $em));

        $repo = $em->repository(UserWithRoles::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        /** @var AbstractEntityRepository<UserWithRoles> $repo */
        $users = $repo->with(['roles'])->all();
        $items = $users->all();

        self::assertCount(1, $items);
        self::assertCount(2, $items[0]->roles->all());
        self::assertSame(['admin', 'user'], array_map(fn (Role $r) => $r->name, $items[0]->roles->all()));
    }
}
