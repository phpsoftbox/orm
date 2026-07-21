<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\ConnectionEntityManagerFactory;
use PhpSoftBox\Orm\EntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(ConnectionEntityManagerFactory::class)]
final class ConnectionEntityManagerFactoryTest extends TestCase
{
    #[Test]
    public function createUsesWriteConnectionByDefault(): void
    {
        $connection  = $this->createMock(ConnectionInterface::class);
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
        $connection  = $this->createMock(ConnectionInterface::class);
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
        $connection  = $this->createMock(ConnectionInterface::class);
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
        $connection  = $this->createMock(ConnectionInterface::class);
        $connections = $this->createMock(ConnectionManagerInterface::class);
        $connections->expects(self::exactly(2))->method('write')->willReturn($connection);

        $factory = new ConnectionEntityManagerFactory($connections);

        $dispatcher = $factory->create('dispatcher');
        $tenant     = $factory->create('tenant');

        self::assertSame($factory->runtimeRegistry(), $dispatcher->unitOfWork()->runtimeRegistry());
        self::assertSame($factory->runtimeRegistry(), $tenant->unitOfWork()->runtimeRegistry());
        self::assertNotSame($dispatcher->unitOfWork(), $tenant->unitOfWork());
    }
}
