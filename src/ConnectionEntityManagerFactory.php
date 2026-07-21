<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContextResolverInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\Contracts\ConnectionEntityManagerFactoryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityRuntimeRegistryInterface;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeResolverInterface;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\UnitOfWork\EntityHeap;
use PhpSoftBox\Orm\UnitOfWork\EntityRuntimeRegistry;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;

final readonly class ConnectionEntityManagerFactory implements ConnectionEntityManagerFactoryInterface
{
    private EntityRuntimeRegistryInterface $runtimeRegistry;

    public function __construct(
        private ConnectionManagerInterface $connections,
        private ?MetadataProviderInterface $metadata = null,
        private ?AutoEntityMapper $mapper = null,
        private ?EntityChangeLoggerInterface $changeLogger = null,
        private ?EntityChangeContextResolverInterface $changeContextResolver = null,
        private array $changelogIgnoredFields = [],
        private ?RelationScopeResolverInterface $relationScopeResolver = null,
        ?EntityRuntimeRegistryInterface $runtimeRegistry = null,
    ) {
        $this->runtimeRegistry = $runtimeRegistry ?? new EntityRuntimeRegistry();
    }

    public function runtimeRegistry(): EntityRuntimeRegistryInterface
    {
        return $this->runtimeRegistry;
    }

    public function create(string $connectionName = 'default', bool $write = true): EntityManagerInterface
    {
        $connection = $write
            ? $this->connections->write($connectionName)
            : $this->connections->read($connectionName);

        return new EntityManager(
            connection: $connection,
            unitOfWork: new UnitOfWork(new EntityHeap($this->runtimeRegistry)),
            metadata: $this->metadata ?? new AttributeMetadataProvider(),
            mapper: $this->mapper,
            changeLogger: $this->changeLogger,
            changeContextResolver: $this->changeContextResolver,
            changelogIgnoredFields: $this->changelogIgnoredFields,
            relationScopeResolver: $this->relationScopeResolver,
        );
    }
}
