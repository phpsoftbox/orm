<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use DateTimeImmutable;
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

#[CoversClass(EntityManager::class)]
final class PivotEntityIntegrationTest extends TestCase
{
    /**
     * Проверяет, что при BelongsToMany с pivotEntity ORM:
     * - загружает target entities
     * - гидрирует pivot entity из строки pivot-таблицы
     * - устанавливает pivot через HasPivotTrait (setPivot/pivot)
     */
    #[Test]
    public function eagerLoadsBelongsToManyWithPivotEntity(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            "
                CREATE TABLE users_pivot_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            "
        );

        $conn->execute(
            "
                CREATE TABLE roles_pivot_rel (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            "
        );

        $conn->execute(
            "
                CREATE TABLE user_role_pivot_rel (
                    user_id INTEGER NOT NULL,
                    role_id INTEGER NOT NULL,
                    created_datetime VARCHAR(64) NOT NULL
                )
            "
        );

        $conn->execute(
            "
                INSERT INTO users_pivot_rel (id, name) VALUES (1, 'Anton')
            "
        );

        $conn->execute(
            "
                INSERT INTO roles_pivot_rel (id, name) VALUES (10, 'admin')
            "
        );

        $conn->execute(
            "
                INSERT INTO user_role_pivot_rel (user_id, role_id, created_datetime)
                VALUES (1, 10, '2026-01-27T12:00:00+00:00')
            "
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

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

        $pivot = $role->pivot();
        self::assertNotNull($pivot);

        // pivot() возвращает EntityInterface, но по PHPDoc/trait'у IDE будет видеть UserRole.
        self::assertSame(1, $pivot->userId);
        self::assertSame(10, $pivot->roleId);
        self::assertInstanceOf(DateTimeImmutable::class, $pivot->createdDatetime);
        self::assertSame('2026-01-27T12:00:00+00:00', $pivot->createdDatetime->format(DATE_ATOM));
    }
}
