<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\UuidEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[CoversClass(EntityManager::class)]
#[CoversClass(AutoEntityMapper::class)]
#[CoversMethod(EntityManager::class, 'flush')]
#[CoversMethod(EntityManager::class, 'find')]
#[CoversMethod(EntityManager::class, 'refresh')]
#[CoversMethod(AutoEntityMapper::class, 'hydrate')]
final class UuidGeneratedValueTest extends TestCase
{
    /**
     * Проверим, что для #[GeneratedValue(strategy: 'uuid')] ORM генерирует UUID при INSERT
     * и записывает его в неинициализированное readonly-свойство.
     *
     * @see EntityManager::flush()
     */
    #[Test]
    public function flushGeneratesUuidForReadonlyId(): void
    {
        $conn = $this->connection();
        $em   = new EntityManager(connection: $conn);

        $entity = new UuidEntity('first');

        $em->persist($entity);
        $em->flush();

        self::assertInstanceOf(UuidInterface::class, $entity->id());

        $row = $conn->fetchOne('SELECT id, name FROM uuid_entities');
        self::assertSame($entity->id()->toString(), $row['id']);
        self::assertSame('first', $row['name']);
    }

    /**
     * Проверим, что find() гидрирует readonly UuidInterface id через Reflection.
     *
     * @see EntityManager::find()
     * @see AutoEntityMapper::hydrate()
     */
    #[Test]
    public function findHydratesReadonlyUuidId(): void
    {
        $conn = $this->connection();
        $uuid = Uuid::uuid7();
        $conn->execute(
            'INSERT INTO uuid_entities (id, name) VALUES (:id, :name)',
            ['id' => $uuid->toString(), 'name' => 'stored'],
        );

        $em = new EntityManager(connection: $conn);

        $entity = $em->find(UuidEntity::class, $uuid);

        self::assertInstanceOf(UuidEntity::class, $entity);
        self::assertTrue($uuid->equals($entity->id()));
        self::assertSame('stored', $entity->name);
    }

    /**
     * Проверим, что refresh() работает для сущности с readonly id: id не трогается, остальные колонки перечитываются.
     *
     * @see EntityManager::refresh()
     */
    #[Test]
    public function refreshReloadsColumnsOfEntityWithReadonlyId(): void
    {
        $conn = $this->connection();
        $em   = new EntityManager(connection: $conn);

        $entity = new UuidEntity('before');

        $em->persist($entity);
        $em->flush();

        $conn->execute(
            'UPDATE uuid_entities SET name = :name WHERE id = :id',
            ['name' => 'after', 'id' => $entity->id()->toString()],
        );

        $em->refresh($entity);

        self::assertSame('after', $entity->name);
    }

    private function connection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute('CREATE TABLE uuid_entities (id VARCHAR(36) PRIMARY KEY, name VARCHAR(255) NOT NULL)');

        return $conn;
    }
}
