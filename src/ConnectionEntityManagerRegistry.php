<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Orm\Behavior\EventDispatcherInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContextResolverInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\Contracts\EntityAwareEntityManagerRegistryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityRuntimeRegistryInterface;
use PhpSoftBox\Orm\Contracts\ListenerResolverInterface;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeResolverInterface;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use Throwable;

use function trim;

/**
 * Registry EntityManager по connection.
 *
 * Managers создаются через {@see ConnectionEntityManagerFactory} с теми же опциями, что и `new EntityManager(...)`.
 */
final readonly class ConnectionEntityManagerRegistry implements EntityAwareEntityManagerRegistryInterface
{
    private ConnectionEntityManagerFactory $factory;

    public function __construct(
        ConnectionManagerInterface $connections,
        ?MetadataProviderInterface $metadata = null,
        ?AutoEntityMapper $mapper = null,
        private string $defaultConnectionName = 'default',
        ?EntityChangeLoggerInterface $changeLogger = null,
        ?EntityChangeContextResolverInterface $changeContextResolver = null,
        array $changelogIgnoredFields = [],
        ?RelationScopeResolverInterface $relationScopeResolver = null,
        ?EntityRuntimeRegistryInterface $runtimeRegistry = null,
        ?EventDispatcherInterface $events = null,
        ?ListenerResolverInterface $listenerResolver = null,
        ?EntityManagerConfig $config = null,
    ) {
        $this->factory = new ConnectionEntityManagerFactory(
            connections: $connections,
            metadata: $metadata,
            mapper: $mapper,
            changeLogger: $changeLogger,
            changeContextResolver: $changeContextResolver,
            changelogIgnoredFields: $changelogIgnoredFields,
            relationScopeResolver: $relationScopeResolver,
            runtimeRegistry: $runtimeRegistry,
            events: $events,
            listenerResolver: $listenerResolver,
            config: $config,
        );
    }

    public function runtimeRegistry(): EntityRuntimeRegistryInterface
    {
        return $this->factory->runtimeRegistry();
    }

    public function default(bool $write = true): EntityManagerInterface
    {
        return $this->forConnection($this->defaultConnectionName, $write);
    }

    public function forConnection(string $connectionName, bool $write = true): EntityManagerInterface
    {
        $connectionName = trim($connectionName);
        if ($connectionName === '') {
            $connectionName = $this->defaultConnectionName;
        }

        return $this->factory->create($connectionName, $write);
    }

    public function forEntity(string $entityClass, bool $write = true): EntityManagerInterface
    {
        $connectionName = $this->connectionNameForEntity($entityClass);
        if ($connectionName === null) {
            return $this->default($write);
        }

        return $this->forConnection($connectionName, $write);
    }

    /**
     * @param class-string $entityClass
     */
    public function connectionNameForEntity(string $entityClass): ?string
    {
        try {
            $meta = $this->factory->metadata()->for($entityClass);
        } catch (Throwable) {
            return null;
        }

        if ($meta->connection === null) {
            return null;
        }

        $connectionName = trim($meta->connection);

        return $connectionName !== '' ? $connectionName : null;
    }
}
