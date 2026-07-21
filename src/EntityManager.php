<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm;

use InvalidArgumentException;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\QueryBuilder\SelectQueryBuilder;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Behavior\Command\AfterCreate;
use PhpSoftBox\Orm\Behavior\Command\AfterDelete;
use PhpSoftBox\Orm\Behavior\Command\AfterForceDelete;
use PhpSoftBox\Orm\Behavior\Command\AfterRestore;
use PhpSoftBox\Orm\Behavior\Command\AfterUpdate;
use PhpSoftBox\Orm\Behavior\Command\EntityCommandInterface;
use PhpSoftBox\Orm\Behavior\Command\MutableEntityState;
use PhpSoftBox\Orm\Behavior\Command\OnCreate;
use PhpSoftBox\Orm\Behavior\Command\OnDelete;
use PhpSoftBox\Orm\Behavior\Command\OnForceDelete;
use PhpSoftBox\Orm\Behavior\Command\OnRestore;
use PhpSoftBox\Orm\Behavior\Command\OnUpdate;
use PhpSoftBox\Orm\Behavior\DefaultEventDispatcher;
use PhpSoftBox\Orm\Behavior\DefaultListenerResolver;
use PhpSoftBox\Orm\Behavior\EventDispatcherInterface;
use PhpSoftBox\Orm\Bulk\EntityBulkWriter;
use PhpSoftBox\Orm\ChangeLog\EntityChangeAction;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContext;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContextResolverInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeRecord;
use PhpSoftBox\Orm\ChangeLog\NullEntityChangeContextResolver;
use PhpSoftBox\Orm\ChangeLog\NullEntityChangeLogger;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\BulkEntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerContextInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\ListenerResolverInterface;
use PhpSoftBox\Orm\Contracts\RepositoryFactoryInterface;
use PhpSoftBox\Orm\Contracts\RepositoryInterface;
use PhpSoftBox\Orm\Contracts\SoftDeleteAwareEntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\UnitOfWorkInterface;
use PhpSoftBox\Orm\Exception\EntityPersistException;
use PhpSoftBox\Orm\Exception\RepositoryNotRegisteredException;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Metadata\ColumnPropertyMapperInterface;
use PhpSoftBox\Orm\Metadata\MetadataColumnPropertyMapper;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Metadata\RelationMetadata;
use PhpSoftBox\Orm\Persistence\DefaultEntityPersister;
use PhpSoftBox\Orm\Persistence\EntityPersisterInterface;
use PhpSoftBox\Orm\QueryBuilder\OrmSelectQueryBuilder;
use PhpSoftBox\Orm\Relation\PivotRelationManager;
use PhpSoftBox\Orm\Relation\PivotRelationWriter;
use PhpSoftBox\Orm\Relation\Scope\DefaultRelationScopeResolver;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeInterface;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeQuery;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeResolverInterface;
use PhpSoftBox\Orm\Repository\AbstractRepository;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Repository\DefaultRepositoryResolver;
use PhpSoftBox\Orm\Repository\GenericEntityRepository;
use PhpSoftBox\Orm\Repository\RepositoryClassFactory;
use PhpSoftBox\Orm\UnitOfWork\EntityState;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use Ramsey\Uuid\UuidInterface;
use Throwable;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_values;
use function explode;
use function in_array;
use function is_array;
use function is_callable;
use function is_iterable;
use function is_object;
use function is_scalar;
use function is_string;
use function method_exists;
use function property_exists;
use function sort;
use function spl_object_id;
use function strtolower;
use function trim;
use function ucfirst;

final class EntityManager implements EntityManagerContextInterface
{
    private const string CHANGELOG_REDACTED_VALUE = '[redacted]';

    /**
     * @var array<class-string, RepositoryInterface>
     */
    private array $repositories = [];

    private readonly MetadataProviderInterface $metadata;

    private readonly RepositoryFactoryInterface $repositoryFactory;

    private readonly EntityPersisterInterface $persister;

    private readonly AutoEntityMapper $mapper;

    private readonly EventDispatcherInterface $events;

    /**
     * @var array<class-string, object>
     */
    private array $listenerInstances = [];

    private readonly ListenerResolverInterface $listenerResolver;

    private readonly ColumnPropertyMapperInterface $columnPropertyMapper;

    private readonly EntityChangeLoggerInterface $changeLogger;

    private readonly EntityChangeContextResolverInterface $changeContextResolver;

    /**
     * @var array<string, true>
     */
    private readonly array $changelogIgnoredFields;

    private readonly RelationScopeResolverInterface $relationScopeResolver;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly UnitOfWorkInterface $unitOfWork = new UnitOfWork(),
        ?MetadataProviderInterface $metadata = null,
        ?RepositoryFactoryInterface $repositoryFactory = null,
        ?AutoEntityMapper $mapper = null,
        ?EntityPersisterInterface $persister = null,
        ?EventDispatcherInterface $events = null,
        ?ListenerResolverInterface $listenerResolver = null,
        ?EntityManagerConfig $config = null,
        ?EntityChangeLoggerInterface $changeLogger = null,
        ?EntityChangeContextResolverInterface $changeContextResolver = null,
        array $changelogIgnoredFields = [],
        ?RelationScopeResolverInterface $relationScopeResolver = null,
    ) {
        $config ??= new EntityManagerConfig();

        $this->metadata = $metadata ?? new AttributeMetadataProvider(
            namingConvention: $config->namingConvention,
        );

        $this->columnPropertyMapper = new MetadataColumnPropertyMapper($this->metadata);

        $this->mapper = $mapper ?? new AutoEntityMapper(
            metadata: $this->metadata,
            typeCaster: new DefaultTypeCasterFactory()->create(),
            optionsManager: new TypeCastOptionsManager(),
        );

        $this->persister = $persister ?? new DefaultEntityPersister(
            connection: $this->connection,
            metadata: $this->metadata,
            mapper: $this->mapper,
        );

        $this->events = $events ?? new DefaultEventDispatcher();

        // Встроенные listeners/behaviors (опционально)
        if ($config->enableBuiltInListeners && $this->events instanceof DefaultEventDispatcher) {
            $registry = $config->resolveBuiltInRegistry($this->metadata);
            foreach ($registry->listeners() as $listener) {
                $this->events->registerListenerObject($listener);
            }
        }

        $this->listenerResolver       = $listenerResolver ?? new DefaultListenerResolver();
        $this->changeLogger           = $changeLogger ?? new NullEntityChangeLogger();
        $this->changeContextResolver  = $changeContextResolver ?? new NullEntityChangeContextResolver();
        $this->changelogIgnoredFields = $this->normalizeChangelogIgnoredFields($changelogIgnoredFields);
        $this->relationScopeResolver  = $relationScopeResolver ?? new DefaultRelationScopeResolver();

        if ($repositoryFactory !== null) {
            $this->repositoryFactory = $repositoryFactory;
        } else {
            // По умолчанию: резолв репозиториев через цепочку стратегий.
            $resolver = new DefaultRepositoryResolver();

            $this->repositoryFactory = new RepositoryClassFactory($this->metadata, $resolver);
        }
    }

    public function connection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function metadata(): MetadataProviderInterface
    {
        return $this->metadata;
    }

    public function mapper(): AutoEntityMapper
    {
        return $this->mapper;
    }

    public function persister(): EntityPersisterInterface
    {
        return $this->persister;
    }

    public function unitOfWork(): UnitOfWorkInterface
    {
        return $this->unitOfWork;
    }

    /**
     * Регистрирует загруженные сущности как managed и фиксирует snapshot
     * для корректного dirty-checking/changelog diff.
     *
     * @param EntityInterface|iterable<EntityInterface> $entities
     */
    public function trackHydratedEntities(EntityInterface|iterable $entities): void
    {
        $this->manageHydratedEntities($entities instanceof EntityInterface ? [$entities] : $entities);
    }

    /**
     * @param iterable<EntityInterface> $entities
     */
    public function manageHydratedEntities(iterable $entities): EntityCollection
    {
        $managed = [];

        foreach ($entities as $entity) {
            if (!$entity instanceof EntityInterface) {
                continue;
            }

            if ($entity->id() !== null) {
                $cached = $this->unitOfWork->findManaged($entity::class, $entity->id());
                if ($cached !== null) {
                    $managed[] = $cached;
                    continue;
                }
            }

            $this->trackHydratedEntity($entity);
            $managed[] = $entity;
        }

        return new EntityCollection($managed);
    }

    /**
     * @param class-string $entityClass
     */
    public function registerRepository(string $entityClass, RepositoryInterface $repository): void
    {
        $this->repositories[$entityClass] = $repository;
    }

    public function repository(string $entityClass): RepositoryInterface
    {
        if (isset($this->repositories[$entityClass])) {
            return $this->repositories[$entityClass];
        }

        // попытка auto-resolve по #[Entity] и соглашению namespace
        try {
            $repo                             = $this->repositoryFactory->create($entityClass, $this);
            $this->repositories[$entityClass] = $repo;

            return $repo;
        } catch (RepositoryNotRegisteredException) {
            throw new RepositoryNotRegisteredException('Repository not registered for entity: ' . $entityClass);
        }
    }

    public function bulkRepository(string $entityClass): BulkEntityRepositoryInterface
    {
        return $this->resolveBulkRepository($entityClass);
    }

    public function bulk(string $entityClass): EntityBulkWriter
    {
        return new EntityBulkWriter(
            orm: $this,
            connection: $this->connection,
            metadata: $this->metadata,
            unitOfWork: $this->unitOfWork,
            events: $this->events,
            entityClass: $entityClass,
        );
    }

    public function persist(EntityInterface $entity): void
    {
        $repo = $this->repository($entity::class);

        if ($repo instanceof EntityRepositoryInterface) {
            $state = $this->unitOfWork->resolveForPersist($entity, $repo);
            $state === EntityState::New
                ? $this->unitOfWork->markNew($entity)
                : $this->unitOfWork->markManaged($entity);
        } else {
            $entity->id() === null
                ? $this->unitOfWork->markNew($entity)
                : $this->unitOfWork->markManaged($entity);
        }

        $this->unitOfWork->schedulePersist($entity);
    }

    public function remove(EntityInterface $entity): void
    {
        $this->unitOfWork->markRemoved($entity);
        $this->unitOfWork->scheduleRemove($entity);
    }

    public function forceRemove(EntityInterface $entity): void
    {
        $this->unitOfWork->markRemoved($entity);
        $this->unitOfWork->scheduleForceRemove($entity);
    }

    public function restore(EntityInterface $entity): void
    {
        $meta = $this->metadata->for($entity::class);
        if ($meta->softDelete === null) {
            throw new InvalidArgumentException('Cannot restore entity without SoftDelete behavior.');
        }

        if ($entity->id() === null) {
            throw new InvalidArgumentException('Cannot restore entity without id().');
        }

        $this->unitOfWork->markManaged($entity);
        $this->unitOfWork->scheduleRestore($entity);
    }

    public function flush(): void
    {
        $context = $this->changeContextResolver->resolve();
        /** @var list<EntityChangeRecord> $changeRecords */
        $changeRecords = [];

        $this->connection->transaction(function () use (&$changeRecords, $context): void {
            // 0) FORCE DELETE
            foreach ($this->unitOfWork->scheduledForceDeletes() as $entity) {
                $state  = $this->makeState($entity);
                $before = $this->snapshotOrExtract($entity);

                $this->dispatch($entity, new OnForceDelete($this, $entity, $state));
                $this->persister->forceDelete($entity);
                $this->dispatch($entity, new AfterForceDelete($this, $entity, $state));

                $changeRecords[] = $this->buildChangeRecord(
                    action: EntityChangeAction::ForceDelete,
                    entity: $entity,
                    before: $before,
                    after: [],
                    context: $context,
                );

                $this->unitOfWork->markRemoved($entity);
            }

            // 1) INSERT
            foreach ($this->unitOfWork->scheduledInserts() as $entity) {
                $state = $this->makeState($entity);

                $this->dispatch($entity, new OnCreate($this, $entity, $state));
                $this->assertRequiredState($entity, $state, 'insert');
                $this->persister->insert($entity, $state->getData());
                $this->dispatch($entity, new AfterCreate($this, $entity, $state));

                $after = $this->snapshotOrExtract($entity);
                if ($after === []) {
                    $after = $state->getData();
                }

                $changeRecords[] = $this->buildChangeRecord(
                    action: EntityChangeAction::Create,
                    entity: $entity,
                    before: [],
                    after: $after,
                    context: $context,
                );

                $this->trackHydratedEntity($entity);
            }

            // 2) RESTORE
            foreach ($this->unitOfWork->scheduledRestores() as $entity) {
                $before = $this->snapshotOrExtract($entity);
                $state  = $this->makeState($entity);
                $this->applyRestoreState($entity, $state);

                $this->dispatch($entity, new OnRestore($this, $entity, $state));
                $this->applyRestoreState($entity, $state);
                $this->persister->restore($entity, $state->getData());
                $this->dispatch($entity, new AfterRestore($this, $entity, $state));
                $this->applyRestoreState($entity, $state);

                $changeRecords[] = $this->buildChangeRecord(
                    action: EntityChangeAction::Restore,
                    entity: $entity,
                    before: $before,
                    after: $state->getData(),
                    context: $context,
                );

                $this->takeRestoredSnapshot($entity, $before);
            }

            // 3) UPDATE (с dirty-checking)
            foreach ($this->unitOfWork->scheduledUpdates() as $entity) {
                $needsUpdate = true;

                try {
                    $data        = $this->mapper->extract($entity);
                    $needsUpdate = $this->unitOfWork->isDirty($entity, $data);
                } catch (InvalidArgumentException) {
                    $needsUpdate = true;
                }

                if (!$needsUpdate) {
                    continue;
                }

                $before = $this->snapshotOrExtract($entity);
                $state  = $this->makeState($entity);

                $this->dispatch($entity, new OnUpdate($this, $entity, $state));
                $this->assertRequiredState($entity, $state, 'update');
                $this->persister->update($entity, $state->getData());
                $this->dispatch($entity, new AfterUpdate($this, $entity, $state));

                $changeRecords[] = $this->buildChangeRecord(
                    action: EntityChangeAction::Update,
                    entity: $entity,
                    before: $before,
                    after: $state->getData(),
                    context: $context,
                );

                $this->trackHydratedEntity($entity);
            }

            // 4) DELETE
            foreach ($this->unitOfWork->scheduledDeletes() as $entity) {
                $state  = $this->makeState($entity);
                $before = $this->snapshotOrExtract($entity);

                $this->dispatch($entity, new OnDelete($this, $entity, $state));
                $this->persister->delete($entity);
                $this->dispatch($entity, new AfterDelete($this, $entity, $state));

                $changeRecords[] = $this->buildChangeRecord(
                    action: EntityChangeAction::Delete,
                    entity: $entity,
                    before: $before,
                    after: [],
                    context: $context,
                );

                $this->unitOfWork->markRemoved($entity);
            }

            $this->unitOfWork->clearScheduledOperations();
        });

        $this->publishChangeRecords($changeRecords);
    }

    /**
     * Подгружает relations и записывает их в свойства сущностей.
     */
    public function load(EntityInterface|iterable $entities, string|array $relations): void
    {
        $this->loadRelations($entities, $relations, onlyMissing: false);
    }

    public function loadMissing(EntityInterface|iterable $entities, string|array $relations): void
    {
        $this->loadRelations($entities, $relations, onlyMissing: true);
    }

    public function reload(EntityInterface|iterable $entities, string|array $relations): void
    {
        $this->loadRelations($entities, $relations, onlyMissing: false);
    }

    private function loadRelations(
        EntityInterface|iterable $entities,
        string|array $relations,
        bool $onlyMissing,
    ): void {
        $entityList = [];
        if ($entities instanceof EntityInterface) {
            $entityList = [$entities];
        } else {
            foreach ($entities as $e) {
                if ($e instanceof EntityInterface) {
                    $entityList[] = $e;
                }
            }
        }

        if ($entityList === []) {
            return;
        }

        $relationList = is_array($relations) ? $relations : [$relations];

        $tree = $this->buildRelationTree($relationList);

        foreach ($tree as $root => $children) {
            $entitiesToLoad = $entityList;
            if ($onlyMissing) {
                $entitiesToLoad = array_values(array_filter(
                    $entityList,
                    fn (EntityInterface $entity): bool => !$this->unitOfWork->isRelationLoaded($entity, $root),
                ));
            }

            if ($entitiesToLoad !== []) {
                $this->loadRelation($entitiesToLoad, $root);
            }

            if ($children !== []) {
                /** @var list<EntityInterface> $nextEntities */
                $nextEntities = [];

                foreach ($entityList as $entity) {
                    $v = $this->readProperty($entity, $root);

                    if ($v instanceof EntityInterface) {
                        $nextEntities[] = $v;
                        continue;
                    }

                    if ($v instanceof EntityCollection) {
                        foreach ($v->all() as $item) {
                            if ($item instanceof EntityInterface) {
                                $nextEntities[] = $item;
                            }
                        }
                        continue;
                    }

                    if (is_iterable($v)) {
                        foreach ($v as $item) {
                            if ($item instanceof EntityInterface) {
                                $nextEntities[] = $item;
                            }
                        }
                    }
                }

                if ($nextEntities !== []) {
                    $this->loadRelations(
                        $nextEntities,
                        $this->flattenRelationTree($children),
                        $onlyMissing,
                    );
                }
            }
        }
    }

    /**
     * @param list<string> $relations
     * @return array<string, array> дерево вида ['author' => ['company' => []]]
     */
    private function buildRelationTree(array $relations): array
    {
        $tree = [];

        foreach ($relations as $path) {
            $path = (string) $path;
            if ($path === '') {
                continue;
            }

            $parts = explode('.', $path);
            $node  = & $tree;

            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                if (!isset($node[$part])) {
                    $node[$part] = [];
                }
                $node = & $node[$part];
            }

            unset($node);
        }

        return $tree;
    }

    /**
     * @param array<string, array> $tree
     * @return list<string>
     */
    private function flattenRelationTree(array $tree, string $prefix = ''): array
    {
        $paths = [];

        foreach ($tree as $relation => $children) {
            $path    = $prefix === '' ? (string) $relation : $prefix . '.' . $relation;
            $paths[] = $path;

            if ($children !== []) {
                $paths = array_merge($paths, $this->flattenRelationTree($children, $path));
            }
        }

        return $paths;
    }

    /**
     * @param list<EntityInterface> $entities
     */
    private function loadRelation(array $entities, string $relationProperty): void
    {
        $meta     = $this->metadata->for($entities[0]::class);
        $relation = $meta->relations[$relationProperty] ?? null;
        if (!$relation instanceof RelationMetadata) {
            throw new InvalidArgumentException('Unknown relation: ' . $relationProperty);
        }

        match ($relation->type) {
            'many_to_one'      => $this->loadManyToOne($entities, $relationProperty, $relation),
            'has_one'          => $this->loadHasOne($entities, $relationProperty, $relation),
            'has_many'         => $this->loadHasMany($entities, $relationProperty, $relation),
            'belongs_to_many'  => $this->loadBelongsToMany($entities, $relationProperty, $relation),
            'has_many_through' => $this->loadHasManyThrough($entities, $relationProperty, $relation),
            'morph_to'         => $this->loadMorphTo($entities, $relationProperty, $relation),
            'morph_many'       => $this->loadMorphMany($entities, $relationProperty, $relation),
            default            => throw new InvalidArgumentException('Unsupported relation type: ' . $relation->type),
        };
    }

    /**
     * @param list<EntityInterface> $entities
     */
    private function loadHasOne(array $entities, string $relationProperty, RelationMetadata $relation): void
    {
        if ($relation->foreignKey === null) {
            throw new InvalidArgumentException('HasOne relation must define foreignKey');
        }

        $parentIds = [];
        foreach ($entities as $entity) {
            $id = $this->readProperty($entity, $relation->localKey);
            if (is_object($id) && method_exists($id, 'toString')) {
                $id = $id->toString();
            }
            if ($id !== null && is_scalar($id)) {
                $parentIds[(string) $id] = $id;
            }
        }

        if ($parentIds === []) {
            foreach ($entities as $entity) {
                $this->writeLoadedRelation($entity, $relationProperty, null);
            }

            return;
        }

        $children = $this->findManyByColumnWithScopes(
            entityClass: $relation->targetEntity,
            ids: array_values($parentIds),
            column: $relation->foreignKey,
            scopes: $relation->relationScopes,
        );

        $map = [];

        $fkProperty = $this->columnPropertyMapper->columnToProperty($relation->targetEntity, $relation->foreignKey);

        foreach ($children->all() as $child) {
            $fk = $fkProperty !== null ? $this->readAnyProperty($child, $fkProperty) : null;

            if (is_object($fk) && method_exists($fk, 'toString')) {
                $fk = $fk->toString();
            }
            if ($fk === null || !is_scalar($fk)) {
                continue;
            }

            $map[(string) $fk] = $child;
        }

        foreach ($entities as $entity) {
            $id = $this->readProperty($entity, $relation->localKey);
            if (is_object($id) && method_exists($id, 'toString')) {
                $id = $id->toString();
            }

            $this->writeLoadedRelation(
                $entity,
                $relationProperty,
                ($id !== null && isset($map[(string) $id])) ? $map[(string) $id] : null,
            );
        }
    }

    /**
     * @param list<EntityInterface> $entities
     */
    private function loadManyToOne(array $entities, string $relationProperty, RelationMetadata $relation): void
    {
        if ($relation->joinColumn === null) {
            throw new InvalidArgumentException('ManyToOne relation must define joinColumn');
        }

        $foreignIds = [];
        $joins      = [];

        foreach ($entities as $entity) {
            $fk = $this->readProperty($entity, $relation->joinColumn);
            if (is_object($fk) && method_exists($fk, 'toString')) {
                $fk = $fk->toString();
            }

            if ($fk !== null && is_scalar($fk)) {
                $foreignIds[(string) $fk]      = $fk;
                $joins[spl_object_id($entity)] = (string) $fk;
            }
        }

        if ($foreignIds === []) {
            foreach ($entities as $entity) {
                $this->writeLoadedRelation($entity, $relationProperty, null);
            }

            return;
        }

        $targets = $this->findManyByColumnWithScopes(
            entityClass: $relation->targetEntity,
            ids: array_values($foreignIds),
            column: $relation->referencedColumn,
            scopes: $relation->relationScopes,
        );

        $map = [];
        foreach ($targets->all() as $t) {
            $key = $this->readProperty($t, $relation->referencedColumn);
            if (is_object($key) && method_exists($key, 'toString')) {
                $key = $key->toString();
            }
            if ($key !== null && is_scalar($key)) {
                $map[(string) $key] = $t;
            }
        }

        foreach ($entities as $entity) {
            $fkKey = $joins[spl_object_id($entity)] ?? null;
            $this->writeLoadedRelation($entity, $relationProperty, ($fkKey !== null && isset($map[$fkKey])) ? $map[$fkKey] : null);
        }
    }

    /**
     * @param list<EntityInterface> $entities
     */
    private function loadHasMany(array $entities, string $relationProperty, RelationMetadata $relation): void
    {
        if ($relation->foreignKey === null) {
            throw new InvalidArgumentException('HasMany relation must define foreignKey');
        }

        $parentIds = [];
        foreach ($entities as $entity) {
            $id = $this->readProperty($entity, $relation->localKey);
            if (is_object($id) && method_exists($id, 'toString')) {
                $id = $id->toString();
            }
            if ($id !== null && is_scalar($id)) {
                $parentIds[(string) $id] = $id;
            }
        }

        if ($parentIds === []) {
            foreach ($entities as $entity) {
                $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection([]));
            }

            return;
        }

        $children = $this->findManyByColumnWithScopes(
            entityClass: $relation->targetEntity,
            ids: array_values($parentIds),
            column: $relation->foreignKey,
            scopes: $relation->relationScopes,
        );

        /** @var array<string, list<EntityInterface>> $map */
        $map = [];

        $fkProperty = $this->columnPropertyMapper->columnToProperty($relation->targetEntity, $relation->foreignKey);

        foreach ($children->all() as $child) {
            $fk = $fkProperty !== null ? $this->readAnyProperty($child, $fkProperty) : null;

            if (is_object($fk) && method_exists($fk, 'toString')) {
                $fk = $fk->toString();
            }
            if (!is_scalar($fk)) {
                continue;
            }

            $map[(string) $fk] ??= [];
            $map[(string) $fk][] = $child;
        }

        foreach ($entities as $entity) {
            $id = $this->readProperty($entity, $relation->localKey);
            if (is_object($id) && method_exists($id, 'toString')) {
                $id = $id->toString();
            }

            $list = ($id !== null && isset($map[(string) $id])) ? $map[(string) $id] : [];
            $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection($list));
        }
    }

    private function loadBelongsToMany(array $entities, string $relationProperty, RelationMetadata $relation): void
    {
        if ($relation->pivotTable === null || $relation->foreignPivotKey === null || $relation->relatedPivotKey === null) {
            throw new InvalidArgumentException('BelongsToMany relation must define pivotTable, foreignPivotKey and relatedPivotKey');
        }

        $parentIds = [];
        foreach ($entities as $entity) {
            $id = $this->readProperty($entity, $relation->parentKey);
            if (is_object($id) && method_exists($id, 'toString')) {
                $id = $id->toString();
            }
            if ($id !== null && is_scalar($id)) {
                $parentIds[(string) $id] = $id;
            }
        }

        if ($parentIds === []) {
            foreach ($entities as $entity) {
                $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection([]));
            }

            return;
        }

        // Если pivotEntity указан — забираем всю строку pivot (с extra полями), иначе только два ключа.
        $pivotSelect = ($relation->pivotEntity !== null)
            ? ['*']
            : [$relation->foreignPivotKey, $relation->relatedPivotKey];

        $pivotQuery = $this->connection
            ->query()
            ->select($pivotSelect)
            ->from($relation->pivotTable)
            ->whereIn($relation->foreignPivotKey, array_values($parentIds));

        $this->applyRelationScopes($pivotQuery, $relation->pivotScopes);

        $pivotRows = $pivotQuery->fetchAll();

        /** @var array<string, list<int|string>> $relatedIdsByParent */
        $relatedIdsByParent = [];
        $allRelatedIds      = [];

        // pivot map: parentId -> relatedId -> pivotRow
        /** @var array<string, array<string, array<string, mixed>>> $pivotRowByParentAndRelated */
        $pivotRowByParentAndRelated = [];

        foreach ($pivotRows as $row) {
            $p = $row[$relation->foreignPivotKey] ?? null;
            $r = $row[$relation->relatedPivotKey] ?? null;

            if ($p === null || $r === null || !is_scalar($p) || !is_scalar($r)) {
                continue;
            }

            $pKey = (string) $p;
            $rKey = (string) $r;

            $relatedIdsByParent[$pKey] ??= [];
            $relatedIdsByParent[$pKey][] = $r;
            $allRelatedIds[$rKey]        = $r;

            if ($relation->pivotEntity !== null) {
                $pivotRowByParentAndRelated[$pKey] ??= [];
                $pivotRowByParentAndRelated[$pKey][$rKey] = $row;
            }
        }

        $relatedEntities = $this->findManyByColumnWithScopes(
            entityClass: $relation->targetEntity,
            ids: array_values($allRelatedIds),
            column: $relation->relatedKey,
            scopes: $relation->relationScopes,
        );

        $relatedMap = [];
        foreach ($relatedEntities->all() as $relEntity) {
            $key = $this->readProperty($relEntity, $relation->relatedKey);
            if (is_object($key) && method_exists($key, 'toString')) {
                $key = $key->toString();
            }
            if ($key !== null && is_scalar($key)) {
                $relatedMap[(string) $key] = $relEntity;
            }
        }

        $pivotEntityClass = $relation->pivotEntity;
        $pivotAccessor    = $relation->pivotAccessor ?: 'pivot';
        $pivotSetter      = 'set' . ucfirst($pivotAccessor);

        foreach ($entities as $entity) {
            $id = $this->readProperty($entity, $relation->parentKey);
            if (is_object($id) && method_exists($id, 'toString')) {
                $id = $id->toString();
            }

            $list      = [];
            $parentKey = $id !== null ? (string) $id : null;

            if ($parentKey !== null && isset($relatedIdsByParent[$parentKey])) {
                foreach ($relatedIdsByParent[$parentKey] as $rid) {
                    $ridKey = (string) $rid;
                    if (!isset($relatedMap[$ridKey])) {
                        continue;
                    }

                    $relEntity = $relatedMap[$ridKey];

                    if ($pivotEntityClass !== null && isset($pivotRowByParentAndRelated[$parentKey][$ridKey])) {
                        $pivotRow = $pivotRowByParentAndRelated[$parentKey][$ridKey];
                        $pivot    = $this->mapper->hydrate($pivotEntityClass, $pivotRow);

                        // Устанавливаем pivot в target entity, если есть нужный setter.
                        if (method_exists($relEntity, $pivotSetter)) {
                            $relEntity->{$pivotSetter}($pivot);
                        } elseif ($pivotAccessor === 'pivot' && method_exists($relEntity, 'setPivot')) {
                            // совместимость/фолбэк
                            $relEntity->setPivot($pivot);
                        }
                    }

                    $list[] = $relEntity;
                }
            }

            $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection($list));
        }
    }

    /**
     * @param list<EntityInterface> $entities
     */
    private function loadHasManyThrough(array $entities, string $relationProperty, RelationMetadata $relation): void
    {
        if ($relation->throughEntity === null || $relation->firstKey === null || $relation->secondKey === null) {
            throw new InvalidArgumentException('HasManyThrough relation must define throughEntity, firstKey and secondKey');
        }

        $parentIds = [];
        foreach ($entities as $entity) {
            $id = $this->readProperty($entity, $relation->localKey);
            if (is_object($id) && method_exists($id, 'toString')) {
                $id = $id->toString();
            }
            if ($id !== null && is_scalar($id)) {
                $parentIds[(string) $id] = $id;
            }
        }

        if ($parentIds === []) {
            foreach ($entities as $entity) {
                $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection([]));
            }

            return;
        }

        $throughMeta = $this->metadata->for($relation->throughEntity);

        $throughQuery = $this->connection
            ->query()
            ->select([$relation->firstKey, $relation->secondKey])
            ->from($throughMeta->table)
            ->whereIn($relation->firstKey, array_values($parentIds));

        $this->applyRelationScopes($throughQuery, $relation->throughScopes);

        $throughRows = $throughQuery->fetchAll();

        /** @var array<string, list<int|string>> $targetIdsByParent */
        $targetIdsByParent = [];
        $allTargetIds      = [];

        foreach ($throughRows as $row) {
            $p = $row[$relation->firstKey] ?? null;
            $t = $row[$relation->secondKey] ?? null;

            if (!is_scalar($p) || !is_scalar($t)) {
                continue;
            }

            $pKey = (string) $p;
            $targetIdsByParent[$pKey] ??= [];
            $targetIdsByParent[$pKey][] = $t;
            $allTargetIds[(string) $t]  = $t;
        }

        $targetEntities = $this->findManyByColumnWithScopes(
            entityClass: $relation->targetEntity,
            ids: array_values($allTargetIds),
            column: $relation->targetKey,
            scopes: $relation->relationScopes,
        );

        $targetMap = [];
        foreach ($targetEntities->all() as $target) {
            $key = $this->readProperty($target, $relation->targetKey);
            if (is_object($key) && method_exists($key, 'toString')) {
                $key = $key->toString();
            }
            if ($key !== null && is_scalar($key)) {
                $targetMap[(string) $key] = $target;
            }
        }

        foreach ($entities as $entity) {
            $id = $this->readProperty($entity, $relation->localKey);
            if (is_object($id) && method_exists($id, 'toString')) {
                $id = $id->toString();
            }

            $list = [];
            if ($id !== null && isset($targetIdsByParent[(string) $id])) {
                foreach ($targetIdsByParent[(string) $id] as $tid) {
                    $tidKey = (string) $tid;
                    if (isset($targetMap[$tidKey])) {
                        $list[] = $targetMap[$tidKey];
                    }
                }
            }

            $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection($list));
        }
    }

    /**
     * MorphTo: Comment -> (Post|Video|...).
     * Поддерживает batch-загрузку, группируя сущности по typeColumn.
     *
     * @param list<EntityInterface> $entities
     */
    private function loadMorphTo(array $entities, string $relationProperty, RelationMetadata $relation): void
    {
        if ($relation->morphTypeColumn === null || $relation->morphIdColumn === null) {
            throw new InvalidArgumentException('MorphTo relation must define typeColumn and idColumn');
        }

        /** @var array<string, array<string, int|string>> $idsByType */
        $idsByType = [];

        /** @var array<int, array{type: string, id: int|string}> $refs */
        $refs = [];

        foreach ($entities as $entity) {
            $row = $this->mapper->extract($entity);

            $type = $row[$relation->morphTypeColumn] ?? null;
            $id   = $row[$relation->morphIdColumn] ?? null;

            if (!is_string($type) || $type === '' || $id === null || !is_scalar($id)) {
                $this->writeLoadedRelation($entity, $relationProperty, null);
                continue;
            }

            $idsByType[$type] ??= [];
            $idsByType[$type][(string) $id] = $id;

            $refs[spl_object_id($entity)] = ['type' => $type, 'id' => $id];
        }

        if ($idsByType === []) {
            return;
        }

        /** @var array<string, array<string, EntityInterface>> $resolved */
        $resolved = [];

        foreach ($idsByType as $typeValue => $idsMap) {
            $targetClass = $relation->morphMap[$typeValue] ?? null;
            if (!is_string($targetClass) || $targetClass === '') {
                continue;
            }

            $targetMeta = $this->metadata->for($targetClass);

            $pkProperty = $targetMeta->pkProperties[0] ?? 'id';
            $pkColumn   = $this->columnPropertyMapper->propertyToColumn($targetClass, $pkProperty) ?? $pkProperty;

            $targets = $this->findManyByColumnWithScopes(
                entityClass: $targetClass,
                ids: array_values($idsMap),
                column: $pkColumn,
                scopes: $relation->relationScopes,
            );

            foreach ($targets->all() as $t) {
                $tId = $this->readAnyProperty($t, $pkProperty);

                if (is_object($tId) && method_exists($tId, 'toString')) {
                    $tId = $tId->toString();
                }

                if ($tId !== null && is_scalar($tId)) {
                    $resolved[$typeValue] ??= [];
                    $resolved[$typeValue][(string) $tId] = $t;
                }
            }
        }

        foreach ($entities as $entity) {
            $ref = $refs[spl_object_id($entity)] ?? null;
            if ($ref === null) {
                continue;
            }

            $typeValue = $ref['type'];
            $idKey     = (string) $ref['id'];

            $this->writeLoadedRelation(
                $entity,
                $relationProperty,
                $resolved[$typeValue][$idKey] ?? null,
            );
        }
    }

    /**
     * MorphMany: Post -> comments (Comment.commentable_type = 'post' AND commentable_id IN (...)).
     *
     * @param list<EntityInterface> $entities
     */
    private function loadMorphMany(array $entities, string $relationProperty, RelationMetadata $relation): void
    {
        if ($relation->morphTypeColumn === null || $relation->morphIdColumn === null || $relation->morphTypeValue === null) {
            throw new InvalidArgumentException('MorphMany relation must define typeColumn, idColumn and typeValue');
        }

        $parentIds = [];
        foreach ($entities as $entity) {
            $id = $this->readProperty($entity, $relation->localKey);
            if (is_object($id) && method_exists($id, 'toString')) {
                $id = $id->toString();
            }
            if ($id !== null && is_scalar($id)) {
                $parentIds[(string) $id] = $id;
            }
        }

        if ($parentIds === []) {
            foreach ($entities as $entity) {
                $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection([]));
            }

            return;
        }

        $targetRepo = $this->resolveBulkRepository($relation->targetEntity);

        $targetMeta = $this->metadata->for($relation->targetEntity);

        $query = $this->connection
            ->query()
            ->select(['*'])
            ->from($targetMeta->table)
            ->where(
                $relation->morphTypeColumn . ' = :__psb_morph_type',
                ['__psb_morph_type' => $relation->morphTypeValue],
            )
            ->whereIn($relation->morphIdColumn, array_values($parentIds));

        $this->applyRelationScopes($query, $relation->relationScopes);

        $rows = $query->fetchAll();

        $children = $this->manageHydratedEntities($targetRepo->hydrateManyRows($rows));

        /** @var array<string, list<EntityInterface>> $map */
        $map = [];

        $fkProperty = $this->columnPropertyMapper->columnToProperty($relation->targetEntity, $relation->morphIdColumn);

        foreach ($children->all() as $child) {
            $fk = $fkProperty !== null ? $this->readAnyProperty($child, $fkProperty) : null;

            if (is_object($fk) && method_exists($fk, 'toString')) {
                $fk = $fk->toString();
            }
            if (!is_scalar($fk)) {
                continue;
            }

            $map[(string) $fk] ??= [];
            $map[(string) $fk][] = $child;
        }

        foreach ($entities as $entity) {
            $id = $this->readProperty($entity, $relation->localKey);
            if (is_object($id) && method_exists($id, 'toString')) {
                $id = $id->toString();
            }

            $list = ($id !== null && isset($map[(string) $id])) ? $map[(string) $id] : [];
            $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection($list));
        }
    }

    /**
     * @param list<EntityChangeRecord> $records
     */
    private function publishChangeRecords(array $records): void
    {
        foreach ($records as $record) {
            try {
                $this->changeLogger->log($record);
            } catch (Throwable) {
                // Ошибки changelog не блокируют write-path ORM.
            }
        }
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function buildChangeRecord(
        EntityChangeAction $action,
        EntityInterface $entity,
        array $before,
        array $after,
        EntityChangeContext $context,
    ): EntityChangeRecord {
        $changes      = $this->diffByKeys($before, $after);
        $ignoreFields = $this->resolveChangelogIgnoredFields($entity::class);
        if ($ignoreFields !== []) {
            $before  = $this->maskSensitiveFields($before, $ignoreFields);
            $after   = $this->maskSensitiveFields($after, $ignoreFields);
            $changes = $this->maskSensitiveDiff($changes, $ignoreFields);
        }

        return new EntityChangeRecord(
            action: $action,
            entityClass: $entity::class,
            entityId: $this->normalizeEntityId($entity->id()),
            before: $before,
            after: $after,
            changes: $changes,
            context: $context,
        );
    }

    /**
     * @param class-string $entityClass
     * @return array<string, true>
     */
    private function resolveChangelogIgnoredFields(string $entityClass): array
    {
        $ignored = $this->changelogIgnoredFields;

        try {
            $meta = $this->metadata->for($entityClass);
        } catch (Throwable) {
            return $ignored;
        }

        foreach ($meta->changelog?->ignore ?? [] as $field) {
            $normalized = $this->normalizeSensitiveFieldName($field);
            if ($normalized === null) {
                continue;
            }

            $ignored[$normalized] = true;
        }

        return $ignored;
    }

    /**
     * @param list<array{field: string, before: mixed, after: mixed}> $changes
     * @param array<string, true> $ignoredFields
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    private function maskSensitiveDiff(array $changes, array $ignoredFields): array
    {
        $result = [];
        foreach ($changes as $change) {
            $field = $change['field'];
            if ($this->isSensitiveFieldName($field, $ignoredFields)) {
                $change['before'] = self::CHANGELOG_REDACTED_VALUE;
                $change['after']  = self::CHANGELOG_REDACTED_VALUE;
                $result[]         = $change;

                continue;
            }

            $change['before'] = $this->maskSensitiveFields($change['before'], $ignoredFields);
            $change['after']  = $this->maskSensitiveFields($change['after'], $ignoredFields);
            $result[]         = $change;
        }

        return $result;
    }

    /**
     * @param array<string, true> $ignoredFields
     */
    private function maskSensitiveFields(mixed $value, array $ignoredFields): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitiveFieldName($key, $ignoredFields)) {
                $result[$key] = self::CHANGELOG_REDACTED_VALUE;

                continue;
            }

            $result[$key] = $this->maskSensitiveFields($item, $ignoredFields);
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $fields
     * @return array<string, true>
     */
    private function normalizeChangelogIgnoredFields(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (!is_string($field)) {
                continue;
            }

            $normalized = $this->normalizeSensitiveFieldName($field);
            if ($normalized === null) {
                continue;
            }

            $result[$normalized] = true;
        }

        return $result;
    }

    /**
     * @param array<string, true> $ignoredFields
     */
    private function isSensitiveFieldName(string $field, array $ignoredFields): bool
    {
        $normalized = $this->normalizeSensitiveFieldName($field);
        if ($normalized === null) {
            return false;
        }

        return array_key_exists($normalized, $ignoredFields);
    }

    private function normalizeSensitiveFieldName(string $field): ?string
    {
        $normalized = trim($field);
        if ($normalized === '') {
            return null;
        }

        return strtolower($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotOrExtract(EntityInterface $entity): array
    {
        $snapshot = $this->unitOfWork->snapshot($entity);
        if ($snapshot !== null) {
            return $snapshot->data;
        }

        try {
            return $this->mapper->extract($entity);
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    private function diffByKeys(array $before, array $after): array
    {
        $keys = array_keys(array_merge($before, $after));
        sort($keys);

        $changes = [];
        foreach ($keys as $key) {
            $hasBefore = array_key_exists($key, $before);
            $hasAfter  = array_key_exists($key, $after);

            if (!$hasBefore && !$hasAfter) {
                continue;
            }

            $beforeValue = $hasBefore ? $before[$key] : null;
            $afterValue  = $hasAfter ? $after[$key] : null;

            if ($beforeValue === null && $afterValue === null) {
                continue;
            }

            if ($hasBefore && $hasAfter && $beforeValue === $afterValue) {
                continue;
            }

            $changes[] = [
                'field'  => $key,
                'before' => $beforeValue,
                'after'  => $afterValue,
            ];
        }

        return $changes;
    }

    private function normalizeEntityId(mixed $id): int|string|null
    {
        if ($id === null) {
            return null;
        }

        if (is_object($id) && method_exists($id, 'toString')) {
            return $id->toString();
        }

        if (is_scalar($id)) {
            return $id;
        }

        return null;
    }

    private function readProperty(object $obj, string $property): mixed
    {
        if (!property_exists($obj, $property)) {
            throw new InvalidArgumentException('Property does not exist: ' . $property);
        }

        return $obj->$property;
    }

    private function writeProperty(object $obj, string $property, mixed $value): void
    {
        if (!property_exists($obj, $property)) {
            throw new InvalidArgumentException('Property does not exist: ' . $property);
        }

        $obj->$property = $value;
    }

    private function writeLoadedRelation(EntityInterface $entity, string $property, mixed $value): void
    {
        $this->writeProperty($entity, $property, $value);
        $this->unitOfWork->markRelationLoaded($entity, $property);
    }

    /**
     * Читает свойство, не бросая исключение, если свойства нет (возвращает null).
     */
    private function readAnyProperty(object $obj, string $property): mixed
    {
        return property_exists($obj, $property) ? $obj->$property : null;
    }

    /**
     * @param class-string $entityClass
     */
    private function resolveBulkRepository(string $entityClass): BulkEntityRepositoryInterface
    {
        $repo = $this->repository($entityClass);

        if ($repo instanceof BulkEntityRepositoryInterface) {
            return $repo;
        }

        return new GenericEntityRepository(
            $this->connection,
            $entityClass,
            $this->metadata,
            $this->mapper,
            $this->persister,
        );
    }

    /**
     * @param list<int|string> $ids
     * @param list<class-string<RelationScopeInterface>> $scopes
     */
    private function findManyByColumnWithScopes(string $entityClass, array $ids, string $column, array $scopes = []): EntityCollection
    {
        if ($ids === []) {
            return new EntityCollection([]);
        }

        $targetRepo = $this->resolveBulkRepository($entityClass);

        if ($scopes === []) {
            return $this->manageHydratedEntities($targetRepo->findManyByColumn(
                ids: array_values($ids),
                column: $column,
                withDeleted: false,
            ));
        }

        $targetMeta = $this->metadata->for($entityClass);

        $query = $this->connection
            ->query()
            ->select(['*'])
            ->from($targetMeta->table)
            ->whereIn($column, array_values($ids));

        if ($targetMeta->softDelete !== null) {
            $query->whereNull($targetMeta->softDelete->column);
        }

        $this->applyRelationScopes($query, $scopes);

        return $this->manageHydratedEntities($targetRepo->hydrateManyRows($query->fetchAll()));
    }

    /**
     * @param list<class-string<RelationScopeInterface>> $scopes
     */
    private function applyRelationScopes(SelectQueryBuilder $query, array $scopes, ?string $alias = null): void
    {
        if ($scopes === []) {
            return;
        }

        $scopeQuery = new RelationScopeQuery($query, $alias);

        foreach ($scopes as $scopeClass) {
            $this->relationScopeResolver->resolve($scopeClass)->apply($scopeQuery);
        }
    }


    private function makeState(EntityInterface $entity): MutableEntityState
    {
        /** @var array<string, mixed> $data */
        $data = $this->mapper->extract($entity);

        return new MutableEntityState($data);
    }

    /**
     * @param 'insert'|'update' $operation
     */
    private function assertRequiredState(
        EntityInterface $entity,
        MutableEntityState $state,
        string $operation,
    ): void {
        $meta    = $this->metadata->for($entity::class);
        $data    = $state->getData();
        $columns = $operation === 'insert'
            ? $meta->insertableColumns()
            : $meta->updatableColumns();

        foreach ($columns as $column) {
            if ($operation === 'update' && $column->isId) {
                continue;
            }

            if ($operation === 'insert' && $column->isId && $entity->id() === null) {
                continue;
            }

            if ($column->nullable) {
                continue;
            }

            if (!array_key_exists($column->column, $data) || $data[$column->column] === null) {
                throw EntityPersistException::missingRequiredValue(
                    entityClass: $entity::class,
                    property: $column->property,
                    column: $column->column,
                    operation: $operation,
                );
            }
        }
    }

    private function applyRestoreState(EntityInterface $entity, MutableEntityState $state): void
    {
        $meta = $this->metadata->for($entity::class);
        if ($meta->softDelete === null) {
            throw new InvalidArgumentException('Cannot restore entity without SoftDelete behavior.');
        }

        $state->register($meta->softDelete->column, null);
    }

    /**
     * @param array<string, mixed> $before
     */
    private function takeRestoredSnapshot(EntityInterface $entity, array $before): void
    {
        $meta = $this->metadata->for($entity::class);
        if ($meta->softDelete === null) {
            throw new InvalidArgumentException('Cannot restore entity without SoftDelete behavior.');
        }

        $before[$meta->softDelete->column] = null;
        $this->unitOfWork->takeSnapshot($entity, $before);
    }

    private function dispatch(EntityInterface $entity, EntityCommandInterface $event): void
    {
        try {
            $meta = $this->metadata->for($entity::class);

            foreach ($meta->eventListeners as $listenerClass) {
                if (!isset($this->listenerInstances[$listenerClass])) {
                    $this->listenerInstances[$listenerClass] = $this->listenerResolver->resolve($listenerClass);
                    if ($this->events instanceof DefaultEventDispatcher) {
                        $this->events->registerListenerObject($this->listenerInstances[$listenerClass]);
                    }
                }
            }

            // 2) entity hooks
            foreach ($meta->hooks as $hook) {
                if (!in_array($event::class, $hook->events, true)) {
                    continue;
                }

                $callable = $hook->callable;
                if (is_callable($callable)) {
                    $callable($event);
                }
            }
        } catch (Throwable) {
            // метаданные/хуки не обязательны
        }

        // 3) dispatch на зарегистрированные listener-объекты
        $this->events->dispatch($event);
    }


    /**
     * @param class-string $entityClass
     */
    public function find(string $entityClass, int|string|UuidInterface $id): ?EntityInterface
    {
        // 1st-level cache
        $cached = $this->unitOfWork->findManaged($entityClass, $id);
        if ($cached !== null) {
            return $cached;
        }

        $repo = $this->repository($entityClass);
        if (!$repo instanceof EntityRepositoryInterface) {
            throw new InvalidArgumentException('Repository for entity ' . $entityClass . ' does not support find().');
        }

        $entity = $repo->find($id);
        if ($entity === null) {
            return null;
        }

        $this->unitOfWork->markManaged($entity);

        // 1) Идеальный вариант: репозиторий сам умеет отдавать stable data() для dirty-checking.
        if ($repo instanceof AbstractRepository) {
            $this->unitOfWork->takeSnapshot($entity, $repo->data($entity));

            return $entity;
        }

        // 2) Fallback: пытаемся сделать snapshot через auto-mapper (если сущность маппится атрибутами).
        try {
            $this->unitOfWork->takeSnapshot($entity, $this->mapper->extract($entity));
        } catch (InvalidArgumentException) {
            // unmapped entity - snapshot не делаем
        }

        return $entity;
    }

    /**
     * @param class-string $entityClass
     */
    public function findWithDeleted(string $entityClass, int|string|UuidInterface $id): ?EntityInterface
    {
        $cached = $this->unitOfWork->findManaged($entityClass, $id);
        if ($cached !== null) {
            return $cached;
        }

        $repo = $this->repository($entityClass);
        if (!$repo instanceof SoftDeleteAwareEntityRepositoryInterface) {
            throw new InvalidArgumentException(
                'Repository for entity ' . $entityClass . ' does not support findWithDeleted().',
            );
        }

        $entity = $repo->findWithDeleted($id);
        if ($entity === null) {
            return null;
        }

        $entity = $this->manageHydratedEntities([$entity])->first();
        if ($entity === null) {
            return null;
        }

        if ($repo instanceof AbstractRepository) {
            $this->unitOfWork->takeSnapshot($entity, $repo->data($entity));
        }

        return $entity;
    }

    public function queryFor(string $entityClass, bool $withDeleted = false): OrmSelectQueryBuilder
    {
        $meta = $this->metadata->for($entityClass);
        $qb   = new OrmSelectQueryBuilder(
            entityManager: $this,
            entityClass: $entityClass,
            table: $meta->table,
            softDeleteColumn: $meta->softDelete?->column,
        );

        if ($withDeleted) {
            $qb->withDeleted();
        }

        return $qb;
    }

    public function metadataProvider(): MetadataProviderInterface
    {
        return $this->metadata;
    }

    public function relationScopeResolver(): RelationScopeResolverInterface
    {
        return $this->relationScopeResolver;
    }

    public function refresh(EntityInterface $entity): void
    {
        $id = $entity->id();
        if ($id === null) {
            throw new InvalidArgumentException('Cannot refresh entity without identifier (id is null).');
        }

        $repo = $this->repository($entity::class);

        if (!$repo instanceof EntityRepositoryInterface) {
            throw new InvalidArgumentException('Repository for entity ' . $entity::class . ' does not support refresh().');
        }

        // Читаем строку напрямую из БД, чтобы не попасть на 1st-level cache / IdentityMap.
        $meta = $this->metadata->for($entity::class);

        $pkProperty = $meta->pkProperties[0] ?? 'id';
        $pkColumn   = $this->columnPropertyMapper->propertyToColumn($entity::class, $pkProperty) ?? $pkProperty;

        $pkValue = $id instanceof UuidInterface ? $id->toString() : $id;

        $row = $this->queryFor($entity::class, withDeleted: true)
            ->where($pkColumn . ' = :__orm_refresh_pk', ['__orm_refresh_pk' => $pkValue])
            ->limit(1)
            ->fetchOne();

        if ($row === null) {
            throw new InvalidArgumentException('Cannot refresh entity: row not found for id=' . (is_object($id) && method_exists($id, 'toString') ? $id->toString() : (string) $id));
        }

        // Гидратируем строку тем же repository pipeline, что используется обычными ORM-query.
        // Временный объект намеренно не регистрируем в UnitOfWork: managed identity остаётся прежней.
        $hydrated = $this->bulkRepository($entity::class)->hydrateManyRows([$row])->first();
        if ($hydrated === null || $hydrated::class !== $entity::class) {
            throw new InvalidArgumentException(
                'Cannot refresh entity: repository hydrated an unexpected entity type.',
            );
        }

        // Переносим только mapped columns. Relation properties не копируем из временного объекта:
        // их loaded-state сбрасывается ниже, после чего они могут быть загружены заново.
        foreach (array_keys($meta->columns) as $property) {
            if (property_exists($entity, $property) && property_exists($hydrated, $property)) {
                $entity->{$property} = $hydrated->{$property};
            }
        }

        $this->unitOfWork->forgetLoadedRelations($entity);

        // После refresh сущность считаем Managed, а snapshot обновляем на текущее состояние.
        $this->trackHydratedEntity($entity);
    }

    public function pivot(EntityInterface $owner, string $relationProperty): PivotRelationManager
    {
        $writer = new PivotRelationWriter($this);

        return new PivotRelationManager(
            writer: $writer,
            owner: $owner,
            relationProperty: $relationProperty,
        );
    }

    private function trackHydratedEntity(EntityInterface $entity): void
    {
        $this->unitOfWork->markManaged($entity);

        try {
            $this->unitOfWork->takeSnapshot($entity, $this->mapper->extract($entity));
        } catch (InvalidArgumentException) {
            // unmapped entity - snapshot не делаем
        }
    }
}
