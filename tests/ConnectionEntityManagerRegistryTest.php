<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\ConnectionEntityManagerRegistry;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\Fixtures\TenantUser;
use PhpSoftBox\Orm\Tests\Fixtures\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(ConnectionEntityManagerRegistry::class)]
final class ConnectionEntityManagerRegistryTest extends TestCase
{
    #[Test]
    public function defaultUsesConfiguredDefaultConnection(): void
    {
        $connection  = $this->createMock(ConnectionInterface::class);
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
        $connection  = $this->createMock(ConnectionInterface::class);
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
        $connection  = $this->createMock(ConnectionInterface::class);
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
        $connection  = $this->createMock(ConnectionInterface::class);
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
        $connection  = $this->createMock(ConnectionInterface::class);
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
        $connection  = $this->createMock(ConnectionInterface::class);
        $connections = $this->createMock(ConnectionManagerInterface::class);
        $connections->expects(self::exactly(2))->method('write')->willReturn($connection);

        $registry = new ConnectionEntityManagerRegistry($connections);

        $dispatcher = $registry->forConnection('dispatcher');
        $tenant     = $registry->forConnection('tenant');

        self::assertSame($registry->runtimeRegistry(), $dispatcher->unitOfWork()->runtimeRegistry());
        self::assertSame($registry->runtimeRegistry(), $tenant->unitOfWork()->runtimeRegistry());
        self::assertNotSame($dispatcher->unitOfWork(), $tenant->unitOfWork());
    }
}
