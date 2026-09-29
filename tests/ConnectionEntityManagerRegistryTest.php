<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\Behavior\DefaultEventDispatcher;
use PhpSoftBox\Orm\ConnectionEntityManagerRegistry;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\EventEntity;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\EventEntityListener;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\EntityWithoutTableName;
use PhpSoftBox\Orm\Tests\Fixtures\TenantUser;
use PhpSoftBox\Orm\Tests\Fixtures\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(ConnectionEntityManagerRegistry::class)]
#[CoversMethod(ConnectionEntityManagerRegistry::class, 'forConnection')]
#[CoversMethod(ConnectionEntityManagerRegistry::class, 'default')]
final class ConnectionEntityManagerRegistryTest extends TestCase
{
    #[Test]
    public function defaultUsesConfiguredDefaultConnection(): void
    {
        $connection  = $this->createStub(ConnectionInterface::class);
        $connections = $this->createMock(ConnectionManagerInterface::class);
        $connections
            ->expects(self::once())
            ->method('write')
            ->with('tenant')
            ->willReturn($connection);
        $connections->expects(self::never())->method('read');

        $registry = new ConnectionEntityManagerRegistry(
            connections: $connections,
            defaultConnectionName: 'tenant',
        );

        $entityManager = $registry->default();

        self::assertInstanceOf(EntityManager::class, $entityManager);
        self::assertSame($connection, $entityManager->connection());
    }

    #[Test]
    public function forConnectionUsesReadConnectionWhenWriteFlagIsFalse(): void
    {
        $connection  = $this->createStub(ConnectionInterface::class);
        $connections = $this->createMock(ConnectionManagerInterface::class);
        $connections
            ->expects(self::once())
            ->method('read')
            ->with('analytics')
            ->willReturn($connection);
        $connections->expects(self::never())->method('write');

        $registry = new ConnectionEntityManagerRegistry($connections);

        $entityManager = $registry->forConnection('analytics', write: false);

        self::assertInstanceOf(EntityManager::class, $entityManager);
        self::assertSame($connection, $entityManager->connection());
    }

    #[Test]
    public function forEntityUsesConnectionFromEntityMetadata(): void
    {
        $connection  = $this->createStub(ConnectionInterface::class);
        $connections = $this->createMock(ConnectionManagerInterface::class);
        $connections
            ->expects(self::once())
            ->method('write')
            ->with('tenant')
            ->willReturn($connection);

        $registry = new ConnectionEntityManagerRegistry(
            connections: $connections,
            defaultConnectionName: 'default',
        );

        $entityManager = $registry->forEntity(TenantUser::class);

        self::assertInstanceOf(EntityManager::class, $entityManager);
        self::assertSame($connection, $entityManager->connection());
    }

    #[Test]
    public function forEntityFallsBackToDefaultWhenEntityHasNoConnection(): void
    {
        $connection  = $this->createStub(ConnectionInterface::class);
        $connections = $this->createMock(ConnectionManagerInterface::class);
        $connections
            ->expects(self::once())
            ->method('write')
            ->with('main')
            ->willReturn($connection);

        $registry = new ConnectionEntityManagerRegistry(
            connections: $connections,
            defaultConnectionName: 'main',
        );

        $entityManager = $registry->forEntity(User::class);

        self::assertInstanceOf(EntityManager::class, $entityManager);
        self::assertSame($connection, $entityManager->connection());
    }

    #[Test]
    public function passesGlobalChangelogIgnoredFieldsToEntityManager(): void
    {
        $connection  = $this->createStub(ConnectionInterface::class);
        $connections = $this->createMock(ConnectionManagerInterface::class);
        $connections
            ->expects(self::once())
            ->method('write')
            ->with('default')
            ->willReturn($connection);

        $registry = new ConnectionEntityManagerRegistry(
            connections: $connections,
            changelogIgnoredFields: ['password'],
        );

        $entityManager = $registry->default();

        $rp = new ReflectionProperty(EntityManager::class, 'changelogIgnoredFields');

        /** @var array<string, true> $ignored */
        $ignored = $rp->getValue($entityManager);

        self::assertArrayHasKey('password', $ignored);
    }

    #[Test]
    public function managersShareRegistryRuntimeState(): void
    {
        $connection  = $this->createStub(ConnectionInterface::class);
        $connections = $this->createMock(ConnectionManagerInterface::class);
        $connections->expects(self::exactly(2))->method('write')->willReturn($connection);

        $registry = new ConnectionEntityManagerRegistry($connections);

        $dispatcher = $registry->forConnection('dispatcher');
        $tenant     = $registry->forConnection('tenant');

        self::assertSame($registry->runtimeRegistry(), $dispatcher->unitOfWork()->runtimeRegistry());
        self::assertSame($registry->runtimeRegistry(), $tenant->unitOfWork()->runtimeRegistry());
        self::assertNotSame($dispatcher->unitOfWork(), $tenant->unitOfWork());
    }

    /**
     * Проверим, что EntityManager из registry использует naming convention по умолчанию,
     * как `new EntityManager()`: имя таблицы выводится для сущности без явного table.
     *
     * @see ConnectionEntityManagerRegistry::forConnection()
     */
    #[Test]
    public function forConnectionResolvesTableByNamingConvention(): void
    {
        $connections = $this->createStub(ConnectionManagerInterface::class);
        $connections->method('write')->willReturn($this->sqliteConnection());

        $registry = new ConnectionEntityManagerRegistry($connections);

        $sql = $registry->forConnection('main')->queryFor(EntityWithoutTableName::class)->toSql()['sql'];

        self::assertStringContainsString('FROM "entity_without_table_names"', $sql);
    }

    /**
     * Проверим, что registry передаёт в EntityManager глобальный dispatcher событий.
     *
     * @see ConnectionEntityManagerRegistry::default()
     */
    #[Test]
    public function defaultPassesEventDispatcherToEntityManager(): void
    {
        $connection = $this->sqliteConnection();
        $connection->execute('CREATE TABLE event_entities (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL)');

        $connections = $this->createStub(ConnectionManagerInterface::class);
        $connections->method('write')->willReturn($connection);

        $registry = new ConnectionEntityManagerRegistry(
            connections: $connections,
            events: new DefaultEventDispatcher([new EventEntityListener()]),
        );

        $em = $registry->default();
        $em->persist(new EventEntity(name: 'original'));
        $em->flush();

        self::assertSame('from_listener', $connection->fetchOne('SELECT name FROM event_entities')['name']);
    }

    private function sqliteConnection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new Connection($pdo, new SqliteDriver());
    }
}
