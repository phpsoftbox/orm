<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use LogicException;
use PhpSoftBox\Database\Database;
use PhpSoftBox\Orm\Exception\CompositePrimaryKeyNotSupportedException;
use PhpSoftBox\Orm\Identifier\SingleIdentifier;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Fixtures\User;
use PhpSoftBox\Orm\Tests\Fixtures\UserRepository;
use PhpSoftBox\Orm\Tests\Utils\IntegrationDatabases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(AbstractEntityRepository::class)]
final class AbstractEntityRepositoryTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = IntegrationDatabases::sqliteDatabase();

        // schema for tests
        $this->db->execute('CREATE TABLE users (id VARCHAR(36) PRIMARY KEY, name VARCHAR(255) NOT NULL)');
    }

    /**
     * Проверяет, что find() корректно работает с UUID.
     */
    #[Test]
    public function findWorksWithUuid(): void
    {
        $id = Uuid::uuid7();

        $this->db->execute(
            'INSERT INTO users (id, name) VALUES (:id, :name)',
            ['id' => $id->toString(), 'name' => 'john'],
        );

        $repo = new UserRepository($this->db->connection());

        $user = $repo->find($id);

        self::assertNotNull($user);
        self::assertSame($id->toString(), $user->id->toString());
        self::assertSame('john', $user->name);
    }

    /**
     * Проверяет, что find() умеет принимать id в виде массива ['id' => ...].
     */
    #[Test]
    public function findAcceptsArrayIdentifier(): void
    {
        $id = Uuid::uuid7();

        $this->db->execute(
            'INSERT INTO users (id, name) VALUES (:id, :name)',
            ['id' => $id->toString(), 'name' => 'john'],
        );

        $repo = new UserRepository($this->db->connection());

        $user = $repo->find(['id' => $id]);

        self::assertNotNull($user);
        self::assertSame($id->toString(), $user->id->toString());
    }

    /**
     * Проверяет, что find() умеет принимать IdentifierInterface.
     */
    #[Test]
    public function findAcceptsIdentifierObject(): void
    {
        $id = Uuid::uuid7();

        $this->db->execute(
            'INSERT INTO users (id, name) VALUES (:id, :name)',
            ['id' => $id->toString(), 'name' => 'john'],
        );

        $repo = new UserRepository($this->db->connection());

        $user = $repo->find(new SingleIdentifier('id', $id));

        self::assertNotNull($user);
        self::assertSame($id->toString(), $user->id->toString());
    }

    /**
     * Проверяет, что all() возвращает коллекцию сущностей.
     */
    #[Test]
    public function allReturnsEntityCollection(): void
    {
        $id1 = Uuid::uuid7();
        $id2 = Uuid::uuid7();

        $this->db->execute(
            'INSERT INTO users (id, name) VALUES (:id, :name)',
            ['id' => $id1->toString(), 'name' => 'a'],
        );

        $this->db->execute(
            'INSERT INTO users (id, name) VALUES (:id, :name)',
            ['id' => $id2->toString(), 'name' => 'b'],
        );

        $repo = new UserRepository($this->db->connection());

        $users = $repo->all();

        self::assertSame(2, $users->count());
    }

    /**
     * Проверяет, что если репозиторий объявил composite primary key (pkColumns() вернул больше одного поля),
     * то find() выбрасывает исключение, потому что composite PK пока не поддерживаются.
     */
    #[Test]
    public function findThrowsWhenRepositoryUsesCompositePrimaryKey(): void
    {
        $repo = new class ($this->db->connection()) extends AbstractEntityRepository {
            protected function entityClass(): string
            {
                return User::class;
            }

            protected function table(): string
            {
                return 'users';
            }

            protected function pkColumns(): array
            {
                return ['tenant_id', 'id'];
            }

            protected function hydrate(array $row): \PhpSoftBox\Orm\Contracts\EntityInterface
            {
                throw new LogicException('not needed for this test');
            }

            protected function extract(\PhpSoftBox\Orm\Contracts\EntityInterface $entity): array
            {
                throw new LogicException('not needed for this test');
            }

            protected function doPersist(\PhpSoftBox\Orm\Contracts\EntityInterface $entity): void
            {
                throw new LogicException('not needed for this test');
            }

            protected function doRemove(\PhpSoftBox\Orm\Contracts\EntityInterface $entity): void
            {
                throw new LogicException('not needed for this test');
            }
        };

        $this->expectException(CompositePrimaryKeyNotSupportedException::class);

        $repo->find(1);
    }
}
