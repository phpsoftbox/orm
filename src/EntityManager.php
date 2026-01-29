<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm;

use InvalidArgumentException;
use PhpSoftBox\Collection\Collection;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\QueryBuilder\SelectQueryBuilder;
use PhpSoftBox\Orm\Behavior\Command\EntityCommandInterface;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\RepositoryFactoryInterface;
use PhpSoftBox\Orm\Contracts\RepositoryInterface;
use PhpSoftBox\Orm\Contracts\UnitOfWorkInterface;
use PhpSoftBox\Orm\Exception\RepositoryNotRegisteredException;
use PhpSoftBox\Orm\IdentityMap\WeakIdentityMap;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Metadata\ColumnPropertyMapperInterface;
use PhpSoftBox\Orm\Metadata\MetadataColumnPropertyMapper;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Repository\AbstractRepository;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Repository\DefaultRepositoryResolver;
use PhpSoftBox\Orm\Repository\RepositoryClassFactory;
use PhpSoftBox\Orm\UnitOfWork\EntityState;
use PhpSoftBox\Orm\UnitOfWork\AdvancedUnitOfWork;
use PhpSoftBox\Orm\Identity\EntityKey;
use PhpSoftBox\Orm\Persistence\DefaultEntityPersister;
use PhpSoftBox\Orm\Persistence\EntityPersisterInterface;
use PhpSoftBox\Orm\TypeCasting\DefaultTypeCasterFactory;
use PhpSoftBox\Orm\TypeCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Behavior\Command\AfterCreate;
use PhpSoftBox\Orm\Behavior\Command\AfterDelete;
use PhpSoftBox\Orm\Behavior\Command\AfterForceDelete;
use PhpSoftBox\Orm\Behavior\Command\AfterUpdate;
use PhpSoftBox\Orm\Behavior\Command\MutableEntityState;
use PhpSoftBox\Orm\Behavior\Command\OnCreate;
use PhpSoftBox\Orm\Behavior\Command\OnDelete;
use PhpSoftBox\Orm\Behavior\Command\OnForceDelete;
use PhpSoftBox\Orm\Behavior\Command\OnUpdate;
use PhpSoftBox\Orm\Behavior\DefaultEventDispatcher;
use PhpSoftBox\Orm\Behavior\EventDispatcherInterface;
use PhpSoftBox\Orm\Behavior\DefaultListenerResolver;
use PhpSoftBox\Orm\Contracts\ListenerResolverInterface;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Metadata\RelationMetadata;
use Ramsey\Uuid\UuidInterface;

final class EntityManager implements EntityManagerInterface
{
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

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly UnitOfWorkInterface $unitOfWork = new AdvancedUnitOfWork(new WeakIdentityMap()),
        ?MetadataProviderInterface $metadata = null,
        ?RepositoryFactoryInterface $repositoryFactory = null,
        ?AutoEntityMapper $mapper = null,
        ?EntityPersisterInterface $persister = null,
        ?EventDispatcherInterface $events = null,
        ?ListenerResolverInterface $listenerResolver = null,
        ?EntityManagerConfig $config = null,
    ) {
        $config ??= new EntityManagerConfig();

        $this->metadata = $metadata ?? new AttributeMetadataProvider(
            namingConvention: $config->namingConvention,
        );

        $this->columnPropertyMapper = new MetadataColumnPropertyMapper($this->metadata);

        $this->mapper = $mapper ?? new AutoEntityMapper(
            metadata: $this->metadata,
            typeCaster: (new DefaultTypeCasterFactory())->create(),
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

        $this->listenerResolver = $listenerResolver ?? new DefaultListenerResolver();

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

    public function unitOfWork(): UnitOfWorkInterface
    {
        return $this->unitOfWork;
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
            $repo = $this->repositoryFactory->create($entityClass, $this);
            $this->repositories[$entityClass] = $repo;
            return $repo;
        } catch (RepositoryNotRegisteredException) {
            throw new RepositoryNotRegisteredException('Repository not registered for entity: ' . $entityClass);
        }
    }

    public function persist(EntityInterface $entity): void
    {
        $repo = $this->repository($entity::class);

        if ($repo instanceof EntityRepositoryInterface && $this->unitOfWork instanceof AdvancedUnitOfWork) {
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

    public function flush(): void
    {
        $this->connection->transaction(function (): void {
            // 0) FORCE DELETE
            foreach ($this->unitOfWork->scheduledForceDeletes() as $entity) {
                $state = $this->makeState($entity);

                $this->dispatch($entity, new OnForceDelete($this, $entity, $state));
                $this->persister->forceDelete($entity);
                $this->dispatch($entity, new AfterForceDelete($this, $entity, $state));

                $this->unitOfWork->markRemoved($entity);
            }

            // 1) INSERT
            foreach ($this->unitOfWork->scheduledInserts() as $entity) {
                $state = $this->makeState($entity);

                $this->dispatch($entity, new OnCreate($this, $entity, $state));
                $this->persister->insert($entity, $state->getData());
                $this->dispatch($entity, new AfterCreate($this, $entity, $state));

                try {
                    $this->unitOfWork->takeSnapshot($entity, $this->mapper->extract($entity));
                } catch (InvalidArgumentException) {
                    // ignore
                }

                $this->unitOfWork->markManaged($entity);
            }

            // 2) UPDATE (с dirty-checking)
            foreach ($this->unitOfWork->scheduledUpdates() as $entity) {
                $needsUpdate = true;

                try {
                    $data = $this->mapper->extract($entity);
                    $needsUpdate = $this->unitOfWork->isDirty($entity, $data);
                } catch (InvalidArgumentException) {
                    $needsUpdate = true;
                }

                if (!$needsUpdate) {
                    continue;
                }

                $state = $this->makeState($entity);

                $this->dispatch($entity, new OnUpdate($this, $entity, $state));
                $this->persister->update($entity, $state->getData());
                $this->dispatch($entity, new AfterUpdate($this, $entity, $state));

                try {
                    $this->unitOfWork->takeSnapshot($entity, $this->mapper->extract($entity));
                } catch (InvalidArgumentException) {
                    // ignore
                }

                $this->unitOfWork->markManaged($entity);
            }

            // 3) DELETE
            foreach ($this->unitOfWork->scheduledDeletes() as $entity) {
                $state = $this->makeState($entity);

                $this->dispatch($entity, new OnDelete($this, $entity, $state));
                $this->persister->delete($entity);
                $this->dispatch($entity, new AfterDelete($this, $entity, $state));

                $this->unitOfWork->markRemoved($entity);
            }

            $this->unitOfWork->clearScheduledOperations();
        });
    }

    /**
     * Подгружает связь (пока поддержи��аем только ManyToOne) и записывает в свойство сущности.
     */
    public function load(EntityInterface|iterable $entities, string|array $relations): void
    {
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
            $this->loadRelation($entityList, $root);

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
                    $this->load($nextEntities, array_keys($children));
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
            $node =& $tree;

            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                if (!isset($node[$part])) {
                    $node[$part] = [];
                }
                $node =& $node[$part];
            }

            unset($node);
        }

        return $tree;
    }

    /**
     * @param list<EntityInterface> $entities
     */
    private function loadRelation(array $entities, string $relationProperty): void
    {
        $meta = $this->metadata->for($entities[0]::class);
        $relation = $meta->relations[$relationProperty] ?? null;
        if (!$relation instanceof RelationMetadata) {
            throw new InvalidArgumentException('Unknown relation: ' . $relationProperty);
        }

        match ($relation->type) {
            'many_to_one' => $this->loadManyToOne($entities, $relationProperty, $relation),
            'has_one' => $this->loadHasOne($entities, $relationProperty, $relation),
            'has_many' => $this->loadHasMany($entities, $relationProperty, $relation),
            'belongs_to_many' => $this->loadBelongsToMany($entities, $relationProperty, $relation),
            'has_many_through' => $this->loadHasManyThrough($entities, $relationProperty, $relation),
            default => throw new InvalidArgumentException('Unsupported relation type: ' . $relation->type),
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
                $this->writeProperty($entity, $relationProperty, null);
            }
            return;
        }

        $targetRepo = $this->repository($relation->targetEntity);
        if (!$targetRepo instanceof AbstractEntityRepository) {
            throw new InvalidArgumentException('HasOne requires target repository to extend AbstractEntityRepository (batch hydrate).');
        }

        $children = $targetRepo->findManyByColumn(
            ids: array_values($parentIds),
            column: $relation->foreignKey,
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

            $this->writeProperty(
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
        $joins = [];

        foreach ($entities as $entity) {
            $fk = $this->readProperty($entity, $relation->joinColumn);
            if (is_object($fk) && method_exists($fk, 'toString')) {
                $fk = $fk->toString();
            }

            if ($fk !== null && is_scalar($fk)) {
                $foreignIds[(string) $fk] = $fk;
                $joins[spl_object_id($entity)] = (string) $fk;
            }
        }

        if ($foreignIds === []) {
            foreach ($entities as $entity) {
                $this->writeProperty($entity, $relationProperty, null);
            }
            return;
        }

        $targetRepo = $this->repository($relation->targetEntity);
        if (!$targetRepo instanceof AbstractEntityRepository) {
            throw new InvalidArgumentException('ManyToOne requires target repository to extend AbstractEntityRepository (batch hydrate).');
        }

        $targets = $targetRepo->findManyByColumn(
            ids: array_values($foreignIds),
            column: $relation->referencedColumn,
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
            $this->writeProperty($entity, $relationProperty, ($fkKey !== null && isset($map[$fkKey])) ? $map[$fkKey] : null);
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
                $this->writeProperty($entity, $relationProperty, new EntityCollection([]));
            }
            return;
        }

        $targetRepo = $this->repository($relation->targetEntity);
        if (!$targetRepo instanceof AbstractEntityRepository) {
            throw new InvalidArgumentException('HasMany requires target repository to extend AbstractEntityRepository (batch hydrate).');
        }

        $children = $targetRepo->findManyByColumn(
            ids: array_values($parentIds),
            column: $relation->foreignKey,
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
            $this->writeProperty($entity, $relationProperty, new EntityCollection($list));
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
                $this->writeProperty($entity, $relationProperty, new EntityCollection([]));
            }
            return;
        }

        $pivotRows = $this->connection
            ->query()
            ->select([$relation->foreignPivotKey, $relation->relatedPivotKey])
            ->from($relation->pivotTable)
            ->whereIn($relation->foreignPivotKey, array_values($parentIds))
            ->fetchAll();

        /** @var array<string, list<int|string>> $relatedIdsByParent */
        $relatedIdsByParent = [];
        $allRelatedIds = [];

        foreach ($pivotRows as $row) {
            $p = $row[$relation->foreignPivotKey] ?? null;
            $r = $row[$relation->relatedPivotKey] ?? null;

            if ($p === null || $r === null || !is_scalar($p) || !is_scalar($r)) {
                continue;
            }

            $pKey = (string) $p;
            $relatedIdsByParent[$pKey] ??= [];
            $relatedIdsByParent[$pKey][] = $r;
            $allRelatedIds[(string) $r] = $r;
        }

        $targetRepo = $this->repository($relation->targetEntity);
        if (!$targetRepo instanceof AbstractEntityRepository) {
            throw new InvalidArgumentException('BelongsToMany requires target repository to extend AbstractEntityRepository (batch hydrate).');
        }

        $relatedEntities = $targetRepo->findManyByColumn(
            ids: array_values($allRelatedIds),
            column: $relation->relatedKey,
            withDeleted: false,
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

        foreach ($entities as $entity) {
            $id = $this->readProperty($entity, $relation->parentKey);
            if (is_object($id) && method_exists($id, 'toString')) {
                $id = $id->toString();
            }

            $list = [];
            if ($id !== null && isset($relatedIdsByParent[(string) $id])) {
                foreach ($relatedIdsByParent[(string) $id] as $rid) {
                    $ridKey = (string) $rid;
                    if (isset($relatedMap[$ridKey])) {
                        $list[] = $relatedMap[$ridKey];
                    }
                }
            }

            $this->writeProperty($entity, $relationProperty, new EntityCollection($list));
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
                $this->writeProperty($entity, $relationProperty, new EntityCollection([]));
            }
            return;
        }

        $throughMeta = $this->metadata->for($relation->throughEntity);

        $throughRows = $this->connection
            ->query()
            ->select([$relation->firstKey, $relation->secondKey])
            ->from($throughMeta->table)
            ->whereIn($relation->firstKey, array_values($parentIds))
            ->fetchAll();

        /** @var array<string, list<int|string>> $targetIdsByParent */
        $targetIdsByParent = [];
        $allTargetIds = [];

        foreach ($throughRows as $row) {
            $p = $row[$relation->firstKey] ?? null;
            $t = $row[$relation->secondKey] ?? null;

            if ($p === null || $t === null || !is_scalar($p) || !is_scalar($t)) {
                continue;
            }

            $pKey = (string) $p;
            $targetIdsByParent[$pKey] ??= [];
            $targetIdsByParent[$pKey][] = $t;
            $allTargetIds[(string) $t] = $t;
        }

        $targetRepo = $this->repository($relation->targetEntity);
        if (!$targetRepo instanceof AbstractEntityRepository) {
            throw new InvalidArgumentException('HasManyThrough requires target repository to extend AbstractEntityRepository (batch hydrate).');
        }

        $targetEntities = $targetRepo->findManyByColumn(
            ids: array_values($allTargetIds),
            column: $relation->targetKey,
            withDeleted: false,
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

            $this->writeProperty($entity, $relationProperty, new EntityCollection($list));
        }
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

    /**
     * Читает свойство, не бросая исключение, если свойства нет (возвращает null).
     */
    private function readAnyProperty(object $obj, string $property): mixed
    {
        return property_exists($obj, $property) ? $obj->$property : null;
    }


    private function makeState(EntityInterface $entity): MutableEntityState
    {
        try {
            /** @var array<string, mixed> $data */
            $data = $this->mapper->extract($entity);
        } catch (InvalidArgumentException) {
            $data = [];
        }

        return new MutableEntityState($data);
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
        } catch (\Throwable) {
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
        if ($this->unitOfWork instanceof AdvancedUnitOfWork) {
            $cached = $this->unitOfWork->identityMap()->get(EntityKey::fromParts($entityClass, $id));
            if ($cached !== null) {
                return $cached;
            }
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

    public function queryFor(string $entityClass, bool $withDeleted = false): SelectQueryBuilder
    {
        $meta = $this->metadata->for($entityClass);

        $qb = $this->connection
            ->query()
            ->select()
            ->from($meta->table);

        if (!$withDeleted && $meta->softDelete !== null) {
            $qb->where($meta->softDelete->column . ' IS NULL');
        }

        return $qb;
    }

    public function metadataProvider(): MetadataProviderInterface
    {
        return $this->metadata;
    }
}
