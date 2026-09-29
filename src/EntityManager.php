<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm;

use InvalidArgumentException;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\QueryBuilder\SelectQueryBuilder;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Behavior\CallbackEventDispatcher;
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
use PhpSoftBox\Orm\Behavior\ListenerMethodResolver;
use PhpSoftBox\Orm\Bulk\AbstractAfterBulkWriteCommand;
use PhpSoftBox\Orm\Bulk\AbstractBulkWriteCommand;
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
use PhpSoftBox\Orm\Contracts\UuidGeneratorInterface;
use PhpSoftBox\Orm\Exception\EntityPersistException;
use PhpSoftBox\Orm\Exception\OrmException;
use PhpSoftBox\Orm\Exception\RepositoryNotRegisteredException;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Metadata\ClassMetadata;
use PhpSoftBox\Orm\Metadata\ColumnPropertyMapperInterface;
use PhpSoftBox\Orm\Metadata\MetadataColumnPropertyMapper;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Metadata\RelationMetadata;
use PhpSoftBox\Orm\Persistence\DefaultEntityPersister;
use PhpSoftBox\Orm\Persistence\EntityPersisterInterface;
use PhpSoftBox\Orm\QueryBuilder\OrmSelectQueryBuilder;
use PhpSoftBox\Orm\Relation\PivotRelationManager;
use PhpSoftBox\Orm\Relation\PivotRelationWriter;
use PhpSoftBox\Orm\Relation\RelationKeyResolver;
use PhpSoftBox\Orm\Relation\Scope\DefaultRelationScopeResolver;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeInterface;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeQuery;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeResolverInterface;
use PhpSoftBox\Orm\Repository\AbstractRepository;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Repository\DefaultRepositoryResolver;
use PhpSoftBox\Orm\Repository\GenericEntityRepository;
use PhpSoftBox\Orm\Repository\RepositoryClassFactory;
use PhpSoftBox\Orm\Support\PropertyAccessor;
use PhpSoftBox\Orm\UnitOfWork\EntityState;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PhpSoftBox\Orm\Uuid\RamseyUuidGenerator;
use Ramsey\Uuid\UuidInterface;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

use function array_filter;
use function array_key_exists;
use function array_key_last;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function explode;
use function in_array;
use function is_a;
use function is_array;
use function is_callable;
use function is_iterable;
use function is_object;
use function is_scalar;
use function is_string;
use function method_exists;
use function sort;
use function spl_object_id;
use function strtolower;
use function trim;

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

    /**
     * @var list<object>
     */
    private array $builtInListeners = [];

    private readonly UuidGeneratorInterface $uuidGenerator;

    private readonly RelationKeyResolver $relationKeys;

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

        // Встроенные listeners/behaviors (опционально). Хранятся отдельно и не регистрируются в переданном
        // dispatcher: один dispatcher можно безопасно разделять между несколькими EntityManager.
        if ($config->enableBuiltInListeners) {
            foreach ($config->resolveBuiltInRegistry($this->metadata)->listeners() as $listener) {
                $this->builtInListeners[] = $listener;
            }
        }

        $this->uuidGenerator = $config->uuidGenerator ?? new RamseyUuidGenerator();
        $this->relationKeys  = new RelationKeyResolver($this->metadata);

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

    public function clear(): void
    {
        $this->unitOfWork->clear();
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
            events: new CallbackEventDispatcher($this->dispatchBulk(...)),
            entityClass: $entityClass,
            mapper: $this->mapper,
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
                $this->assignGeneratedUuid($entity);

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

                $before   = $this->snapshotOrExtract($entity);
                $state    = $this->makeState($entity);
                $original = $state->getData();

                $this->dispatch($entity, new OnUpdate($this, $entity, $state));
                $this->assertRequiredState($entity, $state, 'update');
                $this->persister->update($entity, $this->updateChanges($entity, $original, $state->getData()));
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
                $state    = $this->makeState($entity);
                $before   = $this->snapshotOrExtract($entity);
                $original = $state->getData();

                $this->dispatch($entity, new OnDelete($this, $entity, $state));

                // Колонки, которые слушатели добавили в состояние: при soft delete они уходят в тот же UPDATE,
                // при физическом удалении обновлять нечего.
                $additionalColumns = $this->metadata->for($entity::class)->softDelete !== null
                    ? $this->stateChanges($original, $state->getData())
                    : [];

                $this->persister->delete($entity, $additionalColumns);
                $this->dispatch($entity, new AfterDelete($this, $entity, $state));

                $changeRecords[] = $this->buildChangeRecord(
                    action: EntityChangeAction::Delete,
                    entity: $entity,
                    before: $before,
                    after: $additionalColumns,
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
     * Собирает уникальные значения ключа у списка сущностей.
     *
     * @param list<EntityInterface> $entities
     * @return array<string, int|string|float>
     */
    private function collectKeyValues(array $entities, string $key): array
    {
        $values = [];
        foreach ($entities as $entity) {
            $value = $this->relationKeys->readValue($entity, $key);
            if ($value !== null) {
                $values[(string) $value] = $value;
            }
        }

        return $values;
    }

    /**
     * Группирует загруженные сущности по значению ключа (колонка или свойство target-сущности).
     *
     * @param list<EntityInterface> $entities
     * @return array<string, list<EntityInterface>>
     */
    private function groupByKey(array $entities, string $entityClass, string $key): array
    {
        $property = $this->relationKeys->resolve($entityClass, $key)->property;

        $map = [];
        foreach ($entities as $entity) {
            if (!PropertyAccessor::has($entity, $property)) {
                continue;
            }

            $value = RelationKeyResolver::normalize(PropertyAccessor::read($entity, $property));
            if ($value === null) {
                continue;
            }

            $map[(string) $value] ??= [];
            $map[(string) $value][] = $entity;
        }

        return $map;
    }

    /**
     * @param list<EntityInterface> $entities
     */
    private function loadHasOne(array $entities, string $relationProperty, RelationMetadata $relation): void
    {
        if ($relation->foreignKey === null) {
            throw new InvalidArgumentException('HasOne relation must define foreignKey');
        }

        $parentIds = $this->collectKeyValues($entities, $relation->localKey);

        if ($parentIds === []) {
            foreach ($entities as $entity) {
                $this->writeLoadedRelation($entity, $relationProperty, null);
            }

            return;
        }

        $children = $this->findManyByColumnWithScopes(
            entityClass: $relation->targetEntity,
            ids: array_values($parentIds),
            column: $this->relationKeys->column($relation->targetEntity, $relation->foreignKey),
            scopes: $relation->relationScopes,
        );

        $map = $this->groupByKey($children->all(), $relation->targetEntity, $relation->foreignKey);

        foreach ($entities as $entity) {
            $id = $this->relationKeys->readValue($entity, $relation->localKey);

            $this->writeLoadedRelation(
                $entity,
                $relationProperty,
                $id !== null && isset($map[(string) $id]) ? $map[(string) $id][array_key_last($map[(string) $id])] : null,
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

        $foreignIds = $this->collectKeyValues($entities, $relation->joinColumn);

        if ($foreignIds === []) {
            foreach ($entities as $entity) {
                $this->writeLoadedRelation($entity, $relationProperty, null);
            }

            return;
        }

        $targets = $this->findManyByColumnWithScopes(
            entityClass: $relation->targetEntity,
            ids: array_values($foreignIds),
            column: $this->relationKeys->column($relation->targetEntity, $relation->referencedColumn),
            scopes: $relation->relationScopes,
        );

        $map = $this->groupByKey($targets->all(), $relation->targetEntity, $relation->referencedColumn);

        foreach ($entities as $entity) {
            $fk = $this->relationKeys->readValue($entity, $relation->joinColumn);

            $this->writeLoadedRelation(
                $entity,
                $relationProperty,
                $fk !== null && isset($map[(string) $fk]) ? $map[(string) $fk][0] : null,
            );
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

        $parentIds = $this->collectKeyValues($entities, $relation->localKey);

        if ($parentIds === []) {
            foreach ($entities as $entity) {
                $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection([]));
            }

            return;
        }

        $children = $this->findManyByColumnWithScopes(
            entityClass: $relation->targetEntity,
            ids: array_values($parentIds),
            column: $this->relationKeys->column($relation->targetEntity, $relation->foreignKey),
            scopes: $relation->relationScopes,
        );

        $map = $this->groupByKey($children->all(), $relation->targetEntity, $relation->foreignKey);

        foreach ($entities as $entity) {
            $id   = $this->relationKeys->readValue($entity, $relation->localKey);
            $list = $id !== null ? $map[(string) $id] ?? [] : [];

            $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection($list));
        }
    }

    /**
     * @param list<EntityInterface> $entities
     */
    private function loadBelongsToMany(array $entities, string $relationProperty, RelationMetadata $relation): void
    {
        if ($relation->pivotTable === null || $relation->foreignPivotKey === null || $relation->relatedPivotKey === null) {
            throw new InvalidArgumentException('BelongsToMany relation must define pivotTable, foreignPivotKey and relatedPivotKey');
        }

        $parentIds = $this->collectKeyValues($entities, $relation->parentKey);

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

        /** @var array<string, list<array{related: string, row: array<string, mixed>}>> $pivotRowsByParent */
        $pivotRowsByParent = [];
        $allRelatedIds     = [];

        foreach ($pivotRows as $row) {
            $p = $row[$relation->foreignPivotKey] ?? null;
            $r = $row[$relation->relatedPivotKey] ?? null;

            if ($p === null || $r === null || !is_scalar($p) || !is_scalar($r)) {
                continue;
            }

            $pivotRowsByParent[(string) $p] ??= [];
            $pivotRowsByParent[(string) $p][] = ['related' => (string) $r, 'row' => $row];
            $allRelatedIds[(string) $r]       = $r;
        }

        $relatedEntities = $this->findManyByColumnWithScopes(
            entityClass: $relation->targetEntity,
            ids: array_values($allRelatedIds),
            column: $this->relationKeys->column($relation->targetEntity, $relation->relatedKey),
            scopes: $relation->relationScopes,
        );

        $relatedMap = $this->groupByKey($relatedEntities->all(), $relation->targetEntity, $relation->relatedKey);

        foreach ($entities as $entity) {
            $parentKey = $this->relationKeys->readValue($entity, $relation->parentKey);

            $list = [];

            // Pivot хранится в коллекции связи конкретного owner, а не в related-сущности:
            // одна и та же managed-сущность может входить в связи разных owner с разными pivot.
            $pivots = [];

            foreach ($parentKey !== null ? $pivotRowsByParent[(string) $parentKey] ?? [] : [] as $pivotRow) {
                $relEntity = $relatedMap[$pivotRow['related']][0] ?? null;
                if ($relEntity === null) {
                    continue;
                }

                $list[] = $relEntity;

                if ($relation->pivotEntity !== null) {
                    $pivots[spl_object_id($relEntity)] = $this->mapper->hydrate($relation->pivotEntity, $pivotRow['row']);
                }
            }

            $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection($list, $pivots));
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

        $parentIds = $this->collectKeyValues($entities, $relation->localKey);

        if ($parentIds === []) {
            foreach ($entities as $entity) {
                $this->writeLoadedRelation($entity, $relationProperty, new EntityCollection([]));
            }

            return;
        }

        $throughMeta = $this->metadata->for($relation->throughEntity);
        $firstKey    = $this->relationKeys->column($relation->throughEntity, $relation->firstKey);
        $secondKey   = $this->relationKeys->column($relation->throughEntity, $relation->secondKey);

        $throughQuery = $this->connection
            ->query()
            ->select([$firstKey, $secondKey])
            ->from($throughMeta->table)
            ->whereIn($firstKey, array_values($parentIds));

        $this->applyRelationScopes($throughQuery, $relation->throughScopes);

        $throughRows = $throughQuery->fetchAll();

        /** @var array<string, list<int|string>> $targetIdsByParent */
        $targetIdsByParent = [];
        $allTargetIds      = [];

        foreach ($throughRows as $row) {
            $p = $row[$firstKey] ?? null;
            $t = $row[$secondKey] ?? null;

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
            column: $this->relationKeys->column($relation->targetEntity, $relation->targetKey),
            scopes: $relation->relationScopes,
        );

        $targetMap = $this->groupByKey($targetEntities->all(), $relation->targetEntity, $relation->targetKey);

        foreach ($entities as $entity) {
            $id = $this->relationKeys->readValue($entity, $relation->localKey);

            $list = [];
            foreach ($id !== null ? $targetIdsByParent[(string) $id] ?? [] : [] as $tid) {
                foreach ($targetMap[(string) $tid] ?? [] as $target) {
                    $list[] = $target;
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

            $type = $row[$this->relationKeys->column($entity::class, $relation->morphTypeColumn)] ?? null;
            $id   = $row[$this->relationKeys->column($entity::class, $relation->morphIdColumn)] ?? null;

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

        $parentIds = $this->collectKeyValues($entities, $relation->localKey);

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
                $this->relationKeys->column($relation->targetEntity, $relation->morphTypeColumn) . ' = :__psb_morph_type',
                ['__psb_morph_type' => $relation->morphTypeValue],
            )
            ->whereIn(
                $this->relationKeys->column($relation->targetEntity, $relation->morphIdColumn),
                array_values($parentIds),
            );

        $this->applyRelationScopes($query, $relation->relationScopes);

        $rows = $query->fetchAll();

        $children = $this->manageHydratedEntities($targetRepo->hydrateManyRows($rows));

        $map = $this->groupByKey($children->all(), $relation->targetEntity, $relation->morphIdColumn);

        foreach ($entities as $entity) {
            $id   = $this->relationKeys->readValue($entity, $relation->localKey);
            $list = $id !== null ? $map[(string) $id] ?? [] : [];

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
        return PropertyAccessor::read($obj, $property);
    }

    private function writeLoadedRelation(EntityInterface $entity, string $property, mixed $value): void
    {
        PropertyAccessor::write($entity, $property, $value);
        $this->unitOfWork->markRelationLoaded($entity, $property);
    }

    /**
     * Читает свойство, не бросая исключение, если свойства нет (возвращает null).
     */
    private function readAnyProperty(object $obj, string $property): mixed
    {
        return PropertyAccessor::has($obj, $property) ? PropertyAccessor::read($obj, $property) : null;
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


    /**
     * Колонки, которые слушатели события добавили или изменили в состоянии.
     *
     * @param array<string, mixed> $original
     * @param array<string, mixed> $current
     * @return array<string, mixed>
     */
    private function stateChanges(array $original, array $current): array
    {
        $changes = [];
        foreach ($current as $column => $value) {
            if (array_key_exists($column, $original) && $original[$column] === $value) {
                continue;
            }

            $changes[$column] = $value;
        }

        return $changes;
    }

    /**
     * Колонки для UPDATE: отличающиеся от snapshot плюс добавленные/изменённые слушателями `OnUpdate`.
     *
     * Если snapshot нет (сущность не загружалась через ORM), уходят все колонки состояния.
     *
     * @param array<string, mixed> $original состояние до слушателей
     * @param array<string, mixed> $current состояние после слушателей
     * @return array<string, mixed>
     */
    private function updateChanges(EntityInterface $entity, array $original, array $current): array
    {
        $changed = $this->unitOfWork->changedFields($entity, $current);
        if ($changed === null) {
            return $current;
        }

        foreach ($this->stateChanges($original, $current) as $column => $value) {
            $changed[$column] = $value;
        }

        return $changed;
    }

    /**
     * Генерирует UUID для первичного ключа с `#[GeneratedValue(strategy: 'uuid')]`, если он ещё не задан.
     */
    private function assignGeneratedUuid(EntityInterface $entity): void
    {
        $meta = $this->metadata->for($entity::class);
        if ($meta->idGenerationStrategy !== 'uuid' || count($meta->pkProperties) !== 1) {
            return;
        }

        $pkProperty = $meta->pkProperties[0];
        if (PropertyAccessor::read($entity, $pkProperty) !== null) {
            return;
        }

        PropertyAccessor::write($entity, $pkProperty, $this->uuidValueFor($entity, $pkProperty));
    }

    /**
     * UUID в виде, совместимом с типом свойства: объект UuidInterface или строка для `string`-свойства.
     */
    private function uuidValueFor(EntityInterface $entity, string $property): UuidInterface|string
    {
        $uuid = $this->uuidGenerator->generate();
        $type = new ReflectionProperty($entity, $property)->getType();

        $names = match (true) {
            $type instanceof ReflectionNamedType => [$type->getName()],
            $type instanceof ReflectionUnionType => array_map(
                static fn (ReflectionType $item): string => $item instanceof ReflectionNamedType ? $item->getName() : '',
                $type->getTypes(),
            ),
            default => ['mixed'],
        };

        foreach ($names as $name) {
            if ($name === 'mixed' || $name === 'object' || is_a($uuid, $name)) {
                return $uuid;
            }
        }

        return in_array('string', $names, true) ? $uuid->toString() : $uuid;
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

    /**
     * Порядок вызова обработчиков события сущности:
     * 1) `#[Hook]` сущности;
     * 2) `#[EventListener]` сущности (только для событий этой сущности);
     * 3) глобальный dispatcher (`events`);
     * 4) встроенные listeners ORM (Sluggable, Timestamps).
     *
     * Исключения обработчиков и ошибки создания listeners пробрасываются и откатывают транзакцию `flush()`.
     */
    private function dispatch(EntityInterface $entity, EntityCommandInterface $event): void
    {
        $meta = $this->metadataOrNull($entity::class);

        if ($meta !== null) {
            foreach ($meta->hooks as $hook) {
                if (!in_array($event::class, $hook->events, true)) {
                    continue;
                }

                $callable = $hook->callable;
                if (!is_callable($callable)) {
                    throw new OrmException('Hook of entity ' . $entity::class . ' is not callable.');
                }

                $callable($event);
            }

            $this->dispatchToEntityListeners($meta->eventListeners, $event);
        }

        $this->events->dispatch($event);

        foreach ($this->builtInListeners as $listener) {
            ListenerMethodResolver::invoke($listener, $event);
        }
    }

    /**
     * Bulk-события получают `#[EventListener]` сущности и глобальный dispatcher.
     */
    private function dispatchBulk(object $event): void
    {
        if ($event instanceof AbstractBulkWriteCommand || $event instanceof AbstractAfterBulkWriteCommand) {
            $meta = $this->metadataOrNull($event->entityClass());
            if ($meta !== null) {
                $this->dispatchToEntityListeners($meta->eventListeners, $event);
            }
        }

        $this->events->dispatch($event);
    }

    /**
     * @param list<class-string> $listenerClasses
     */
    private function dispatchToEntityListeners(array $listenerClasses, object $event): void
    {
        foreach ($listenerClasses as $listenerClass) {
            $listener = $this->listenerInstances[$listenerClass] ??= $this->listenerResolver->resolve($listenerClass);

            ListenerMethodResolver::invoke($listener, $event);
        }
    }

    /**
     * Метаданные сущности или null, если класс не описан атрибутами (ошибка метаданных — не ошибка события).
     *
     * @param class-string $entityClass
     */
    private function metadataOrNull(string $entityClass): ?ClassMetadata
    {
        try {
            return $this->metadata->for($entityClass);
        } catch (InvalidArgumentException|ReflectionException) {
            return null;
        }
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
        // Инициализированные readonly-свойства (например, id) неизменяемы и не перезаписываются.
        foreach (array_keys($meta->columns) as $property) {
            if (PropertyAccessor::has($entity, $property) && PropertyAccessor::has($hydrated, $property)) {
                PropertyAccessor::write($entity, $property, PropertyAccessor::read($hydrated, $property));
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
