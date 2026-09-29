<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\Behavior\ContainerListenerResolver;
use PhpSoftBox\Orm\ConnectionEntityManagerFactory;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\ArrayContainer;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\DependentListener;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\DependentListenerEntity;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\ListenerNameSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(ConnectionEntityManagerFactory::class)]
#[CoversMethod(ConnectionEntityManagerFactory::class, 'create')]
final class ConnectionEntityManagerFactoryTest extends TestCase
{
    #[Test]
    public function createUsesWriteConnectionByDefault(): void
    {
        $connection  = $this->createStub(ConnectionInterface::class);
        $connections = $this->createMock(ConnectionManagerInterface::class);
        $connections
            ->expects(self::once())
            ->method('write')
            ->with('tenant')
            ->willReturn($connection);
        $connections->expects(self::never())->method('read');

        $factory = new ConnectionEntityManagerFactory($connections);

        $entityManager = $factory->create('tenant');

        self::assertInstanceOf(EntityManager::class, $entityManager);
        self::assertSame($connection, $entityManager->connection());
    }

    #[Test]
    public function createUsesReadConnectionWhenWriteFlagIsFalse(): void
    {
        $connection  = $this->createStub(ConnectionInterface::class);
        $connections = $this->createMock(ConnectionManagerInterface::class);
        $connections
            ->expects(self::once())
            ->method('read')
            ->with('analytics')
            ->willReturn($connection);
        $connections->expects(self::never())->method('write');

        $factory = new ConnectionEntityManagerFactory($connections);

        $entityManager = $factory->create('analytics', write: false);

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

        $factory = new ConnectionEntityManagerFactory(
            connections: $connections,
            changelogIgnoredFields: ['token'],
        );

        $entityManager = $factory->create();

        $rp = new ReflectionProperty(EntityManager::class, 'changelogIgnoredFields');

        /** @var array<string, true> $ignored */
        $ignored = $rp->getValue($entityManager);

        self::assertArrayHasKey('token', $ignored);
    }

    #[Test]
    public function createdManagersShareFactoryRuntimeState(): void
    {
        $connection  = $this->createStub(ConnectionInterface::class);
        $connections = $this->createMock(ConnectionManagerInterface::class);
        $connections->expects(self::exactly(2))->method('write')->willReturn($connection);

        $factory = new ConnectionEntityManagerFactory($connections);

        $dispatcher = $factory->create('dispatcher');
        $tenant     = $factory->create('tenant');

        self::assertSame($factory->runtimeRegistry(), $dispatcher->unitOfWork()->runtimeRegistry());
        self::assertSame($factory->runtimeRegistry(), $tenant->unitOfWork()->runtimeRegistry());
        self::assertNotSame($dispatcher->unitOfWork(), $tenant->unitOfWork());
    }

    /**
     * Проверим, что фабрика передаёт в EntityManager резолвер listeners:
     * listener сущности с зависимостями берётся из контейнера.
     *
     * @see ConnectionEntityManagerFactory::create()
     */
    #[Test]
    public function createPassesListenerResolverToEntityManager(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $connection = new Connection($pdo, new SqliteDriver());

        $connection->execute(
            'CREATE TABLE dependent_listener_entities (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL)',
        );

        $connections = $this->createStub(ConnectionManagerInterface::class);
        $connections->method('write')->willReturn($connection);

        $factory = new ConnectionEntityManagerFactory(
            connections: $connections,
            listenerResolver: new ContainerListenerResolver(new ArrayContainer([
                DependentListener::class => new DependentListener(new ListenerNameSource('from_container')),
            ])),
        );

        $em = $factory->create();
        $em->persist(new DependentListenerEntity(name: 'original'));
        $em->flush();

        self::assertSame('from_container', $connection->fetchOne('SELECT name FROM dependent_listener_entities')['name']);
    }
}
