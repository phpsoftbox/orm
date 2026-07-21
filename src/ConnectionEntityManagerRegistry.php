<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContextResolverInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\Contracts\EntityAwareEntityManagerRegistryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityRuntimeRegistryInterface;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeResolverInterface;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\UnitOfWork\EntityHeap;
use PhpSoftBox\Orm\UnitOfWork\EntityRuntimeRegistry;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use Throwable;

use function trim;

final readonly class ConnectionEntityManagerRegistry implements EntityAwareEntityManagerRegistryInterface
{
    private EntityRuntimeRegistryInterface $runtimeRegistry;

    public function __construct(
        private ConnectionManagerInterface $connections,
        private ?MetadataProviderInterface $metadata = null,
        private ?AutoEntityMapper $mapper = null,
        private string $defaultConnectionName = 'default',
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
            $meta = ($this->metadata ?? new AttributeMetadataProvider())->for($entityClass);
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
