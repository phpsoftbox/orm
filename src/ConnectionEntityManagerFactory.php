<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Orm\Behavior\EventDispatcherInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContextResolverInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\Contracts\ConnectionEntityManagerFactoryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityRuntimeRegistryInterface;
use PhpSoftBox\Orm\Contracts\ListenerResolverInterface;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeResolverInterface;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\UnitOfWork\EntityHeap;
use PhpSoftBox\Orm\UnitOfWork\EntityRuntimeRegistry;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;

/**
 * Создаёт EntityManager для connection из ConnectionManager.
 *
 * Все опции передаются в EntityManager так же, как при `new EntityManager(...)`, поэтому manager из фабрики
 * ведёт себя идентично созданному вручную. Metadata по умолчанию строится с naming convention из `$config`
 * и разделяется между всеми созданными managers.
 */
final readonly class ConnectionEntityManagerFactory implements ConnectionEntityManagerFactoryInterface
{
    private EntityRuntimeRegistryInterface $runtimeRegistry;

    private EntityManagerConfig $config;

    private MetadataProviderInterface $metadata;

    public function __construct(
        private ConnectionManagerInterface $connections,
        ?MetadataProviderInterface $metadata = null,
        private ?AutoEntityMapper $mapper = null,
        private ?EntityChangeLoggerInterface $changeLogger = null,
        private ?EntityChangeContextResolverInterface $changeContextResolver = null,
        private array $changelogIgnoredFields = [],
        private ?RelationScopeResolverInterface $relationScopeResolver = null,
        ?EntityRuntimeRegistryInterface $runtimeRegistry = null,
        private ?EventDispatcherInterface $events = null,
        private ?ListenerResolverInterface $listenerResolver = null,
        ?EntityManagerConfig $config = null,
    ) {
        $this->runtimeRegistry = $runtimeRegistry ?? new EntityRuntimeRegistry();
        $this->config          = $config ?? new EntityManagerConfig();
        $this->metadata        = $metadata ?? new AttributeMetadataProvider(
            namingConvention: $this->config->namingConvention,
        );
    }

    public function runtimeRegistry(): EntityRuntimeRegistryInterface
    {
        return $this->runtimeRegistry;
    }

    public function metadata(): MetadataProviderInterface
    {
        return $this->metadata;
    }

    public function create(string $connectionName = 'default', bool $write = true): EntityManagerInterface
    {
        $connection = $write
            ? $this->connections->write($connectionName)
            : $this->connections->read($connectionName);

        return new EntityManager(
            connection: $connection,
            unitOfWork: new UnitOfWork(new EntityHeap($this->runtimeRegistry)),
            metadata: $this->metadata,
            mapper: $this->mapper,
            events: $this->events,
            listenerResolver: $this->listenerResolver,
            config: $this->config,
            changeLogger: $this->changeLogger,
            changeContextResolver: $this->changeContextResolver,
            changelogIgnoredFields: $this->changelogIgnoredFields,
            relationScopeResolver: $this->relationScopeResolver,
        );
    }
}
