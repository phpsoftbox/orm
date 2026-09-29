<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use DateTimeImmutable;
use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\FixturesPivot\Repository\RoleRepository;
use PhpSoftBox\Orm\Tests\Relations\FixturesPivot\Repository\UserRepository;
use PhpSoftBox\Orm\Tests\Relations\FixturesPivot\Role;
use PhpSoftBox\Orm\Tests\Relations\FixturesPivot\User;
use PhpSoftBox\Orm\Tests\Relations\FixturesPivot\UserRole;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const DATE_ATOM;

#[CoversClass(EntityManager::class)]
#[CoversClass(EntityCollection::class)]
#[CoversMethod(EntityCollection::class, 'pivot')]
final class PivotEntityIntegrationTest extends TestCase
{
    /**
     * Проверяет, что при BelongsToMany с pivotEntity ORM:
     * - загружает target entities
     * - гидрирует pivot entity из строки pivot-таблицы
     * - сохраняет pivot в коллекции связи owner (EntityCollection::pivot()).
     *
     * @see EntityManager::load()
     * @see EntityCollection::pivot()
     */
    #[Test]
    public function eagerLoadsBelongsToManyWithPivotEntity(): void
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
                    created_datetime VARCHAR(64) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                INSERT INTO users_pivot_rel (id, name)
                VALUES (1, \'Anton\')
            ',
        );

        $conn->execute(
            '
                INSERT INTO roles_pivot_rel (id, name)
                VALUES (10, \'admin\')
            ',
        );

        $conn->execute(
            '
                INSERT INTO user_role_pivot_rel (user_id, role_id, created_datetime)
                VALUES (1, 10, \'2026-01-27T12:00:00+00:00\')
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $em->registerRepository(User::class, new UserRepository($conn, $em));
        $em->registerRepository(Role::class, new RoleRepository($conn, $em));

        $repo = $em->repository(User::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        /** @var AbstractEntityRepository<User> $repo */
        $users = $repo->all();
        $items = $users->all();

        // Теперь подгружаем роль и pivot через EntityManager::load()
        $em->load($items, 'roles');

        self::assertCount(1, $items);
        self::assertCount(1, $items[0]->roles->all());

        $role = $items[0]->roles->all()[0];
        self::assertSame('admin', $role->name);

        $pivot = $items[0]->roles->pivot($role);
        self::assertInstanceOf(UserRole::class, $pivot);

        self::assertSame(1, $pivot->userId);
        self::assertSame(10, $pivot->roleId);
        self::assertInstanceOf(DateTimeImmutable::class, $pivot->createdDatetime);
        self::assertSame('2026-01-27T12:00:00+00:00', $pivot->createdDatetime->format(DATE_ATOM));
    }

    /**
     * Проверим, что pivot общей related-сущности не перезаписывается: одна managed-роль у двух пользователей
     * имеет в коллекции каждого пользователя свой pivot.
     *
     * @see EntityManager::load()
     * @see EntityCollection::pivot()
     */
    #[Test]
    public function sharedRelatedEntityKeepsPivotPerOwner(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute('CREATE TABLE users_pivot_rel (id INTEGER PRIMARY KEY, name VARCHAR(255) NOT NULL)');
        $conn->execute('CREATE TABLE roles_pivot_rel (id INTEGER PRIMARY KEY, name VARCHAR(255) NOT NULL)');
        $conn->execute(
            '
                CREATE TABLE user_role_pivot_rel (
                    user_id INTEGER NOT NULL,
                    role_id INTEGER NOT NULL,
                    created_datetime VARCHAR(64) NOT NULL
                )
            ',
        );

        $conn->execute('INSERT INTO users_pivot_rel (id, name) VALUES (1, \'Anton\'), (2, \'Maria\')');
        $conn->execute('INSERT INTO roles_pivot_rel (id, name) VALUES (10, \'admin\')');
        $conn->execute(
            '
                INSERT INTO user_role_pivot_rel (user_id, role_id, created_datetime)
                VALUES
                    (1, 10, \'2026-01-01T00:00:00+00:00\'),
                    (2, 10, \'2026-02-01T00:00:00+00:00\')
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $em->registerRepository(User::class, new UserRepository($conn, $em));
        $em->registerRepository(Role::class, new RoleRepository($conn, $em));

        /** @var AbstractEntityRepository<User> $repo */
        $repo  = $em->repository(User::class);
        $users = $repo->all()->all();

        $em->load($users, 'roles');

        $anton = $users[0];
        $maria = $users[1];

        // Одна и та же managed-роль в обеих коллекциях.
        self::assertSame($anton->roles->first(), $maria->roles->first());

        $antonPivot = $anton->roles->pivot($anton->roles->first());
        $mariaPivot = $maria->roles->pivot($maria->roles->first());

        self::assertInstanceOf(UserRole::class, $antonPivot);
        self::assertInstanceOf(UserRole::class, $mariaPivot);
        self::assertSame(1, $antonPivot->userId);
        self::assertSame(2, $mariaPivot->userId);
        self::assertSame('2026-01-01T00:00:00+00:00', $antonPivot->createdDatetime->format(DATE_ATOM));
        self::assertSame('2026-02-01T00:00:00+00:00', $mariaPivot->createdDatetime->format(DATE_ATOM));
    }
}
