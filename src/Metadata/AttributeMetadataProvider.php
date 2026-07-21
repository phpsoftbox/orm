<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Attributes\JsonCollectionOf;
use PhpSoftBox\DataCasting\Attributes\JsonFactory;
use PhpSoftBox\DataCasting\Attributes\JsonMapOf;
use PhpSoftBox\Orm\Behavior\Attributes\EventListener as BehaviorEventListener;
use PhpSoftBox\Orm\Behavior\Attributes\Hook as BehaviorHook;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsTo;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsToMany;
use PhpSoftBox\Orm\Metadata\Attributes\Changelog;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\FullTextSearch;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\HasMany;
use PhpSoftBox\Orm\Metadata\Attributes\HasManyThrough;
use PhpSoftBox\Orm\Metadata\Attributes\HasOne;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\ManyToOne;
use PhpSoftBox\Orm\Metadata\Attributes\MorphMany;
use PhpSoftBox\Orm\Metadata\Attributes\MorphTo;
use PhpSoftBox\Orm\Metadata\Attributes\NotMapped;
use PhpSoftBox\Orm\Metadata\Attributes\PivotScope;
use PhpSoftBox\Orm\Metadata\Attributes\RelationScope;
use PhpSoftBox\Orm\Metadata\Attributes\Sluggable;
use PhpSoftBox\Orm\Metadata\Attributes\SoftDelete;
use PhpSoftBox\Orm\Metadata\Attributes\ThroughScope;
use PhpSoftBox\Orm\Metadata\Conventions\NamingConventionInterface;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

use function array_key_exists;
use function count;
use function is_a;
use function is_string;
use function trim;

final class AttributeMetadataProvider implements MetadataProviderInterface
{
    /**
     * @var array<class-string, ClassMetadata>
     */
    private array $cache = [];

    public function __construct(
        private readonly ?NamingConventionInterface $namingConvention = null,
    ) {
    }

    /**
     * @throws ReflectionException
     */
    public function for(string $entityClass): ClassMetadata
    {
        return $this->cache[$entityClass] ??= $this->build($entityClass);
    }

    /**
     * @param class-string $entityClass
     * @throws ReflectionException
     */
    private function build(string $entityClass): ClassMetadata
    {
        $rc = new ReflectionClass($entityClass);

        /** @var Entity|null $entityAttr */
        $entityAttr = $this->firstAttrInstance($rc, Entity::class);
        if ($entityAttr === null) {
            throw new InvalidArgumentException('Entity attribute is missing on class: ' . $entityClass);
        }

        $table = $entityAttr->table;
        if ($table === null || $table === '') {
            $naming = $this->namingConvention;
            if ($naming === null) {
                throw new InvalidArgumentException('Entity::table is null for ' . $entityClass . ' and namingConvention is not configured.');
            }
            $table = $naming->entityTable($rc->getShortName());
        }

        $columns              = [];
        $pkProperties         = [];
        $idGenerationStrategy = null;

        foreach ($rc->getProperties() as $prop) {
            if ($this->hasAttr($prop, NotMapped::class)) {
                continue;
            }

            $col = $this->firstAttrInstance($prop, Column::class);
            if ($col === null) {
                continue;
            }

            $isId = $this->hasAttr($prop, Id::class);
            if ($isId) {
                $pkProperties[] = $prop->getName();

                $gen                  = $this->firstAttrInstance($prop, GeneratedValue::class);
                $idGenerationStrategy = $gen?->strategy;
            }

            $columnName   = $col->name ?? $prop->getName();
            $collectionOf = $this->firstAttrInstance($prop, JsonCollectionOf::class);
            $mapOf        = $this->firstAttrInstance($prop, JsonMapOf::class);
            $jsonFactory  = $this->firstAttrInstance($prop, JsonFactory::class);

            if ($collectionOf !== null && $mapOf !== null) {
                throw new InvalidArgumentException(
                    'JSON property cannot use both JsonCollectionOf and JsonMapOf: ' . $entityClass . '::$' . $prop->getName(),
                );
            }

            if (($collectionOf !== null || $mapOf !== null || $jsonFactory !== null) && $col->type !== 'json') {
                throw new InvalidArgumentException(
                    'JSON mapping attributes require #[Column(type: "json")]: ' . $entityClass . '::$' . $prop->getName(),
                );
            }

            $propertyType = $prop->getType();
            if (($collectionOf !== null || $mapOf !== null)
                && (!$propertyType instanceof ReflectionNamedType || $propertyType->getName() !== 'array')
            ) {
                throw new InvalidArgumentException(
                    'JsonCollectionOf/JsonMapOf property must be typed as array: ' . $entityClass . '::$' . $prop->getName(),
                );
            }

            $phpType = $propertyType instanceof ReflectionNamedType && !$propertyType->isBuiltin()
                ? $propertyType->getName()
                : null;

            $columns[$prop->getName()] = new PropertyMetadata(
                property: $prop->getName(),
                column: $columnName,
                type: $col->type,
                length: $col->length,
                nullable: $col->nullable,
                default: $col->default,
                isId: $isId,
                insertable: $col->insertable,
                updatable: $col->updatable,
                options: $col->options,
                phpType: $phpType,
                jsonCollectionItemClass: $collectionOf?->itemClass,
                jsonMapValueClass: $mapOf?->valueClass,
                jsonFactoryClass: $jsonFactory?->factory,
            );
        }

        if (count($pkProperties) === 0) {
            // допускаем сущности без PK (read-only view), но ORM-операции persist/remove могут быть ограничены
            $pkProperties = [];
        }

        $softDeleteMeta = null;
        $softDeleteAttr = $this->firstAttrInstance($rc, SoftDelete::class);
        if ($softDeleteAttr !== null) {
            $softDeleteMeta = new SoftDeleteMetadata(
                entityField: $softDeleteAttr->entityField,
                column: $softDeleteAttr->column,
            );
        }

        $eventListeners = [];
        foreach ($rc->getAttributes(BehaviorEventListener::class) as $attr) {
            /** @var BehaviorEventListener $listener */
            $listener         = $attr->newInstance();
            $eventListeners[] = $listener->listener;
        }

        $hooks = [];
        foreach ($rc->getAttributes(BehaviorHook::class) as $attr) {
            /** @var BehaviorHook $hook */
            $hook    = $attr->newInstance();
            $hooks[] = new HookMetadata(
                callable: $hook->callable,
                events: $hook->events,
            );
        }

        $relations = [];
        foreach ($rc->getProperties() as $prop) {
            if ($this->hasAttr($prop, NotMapped::class)) {
                continue;
            }

            $propName = $prop->getName();

            $hasManyToOne      = $this->hasAttr($prop, ManyToOne::class);
            $hasBelongsTo      = $this->hasAttr($prop, BelongsTo::class);
            $hasHasOne         = $this->hasAttr($prop, HasOne::class);
            $hasHasMany        = $this->hasAttr($prop, HasMany::class);
            $hasBelongsToMany  = $this->hasAttr($prop, BelongsToMany::class);
            $hasHasManyThrough = $this->hasAttr($prop, HasManyThrough::class);
            $hasMorphTo        = $this->hasAttr($prop, MorphTo::class);
            $hasMorphMany      = $this->hasAttr($prop, MorphMany::class);

            $relationScopes = $this->relationScopeClasses($prop, RelationScope::class);
            $pivotScopes    = $this->relationScopeClasses($prop, PivotScope::class);
            $throughScopes  = $this->relationScopeClasses($prop, ThroughScope::class);

            $hasRelation = $hasManyToOne
                || $hasBelongsTo
                || $hasHasOne
                || $hasHasMany
                || $hasBelongsToMany
                || $hasHasManyThrough
                || $hasMorphTo
                || $hasMorphMany;

            if (!$hasRelation && ($relationScopes !== [] || $pivotScopes !== [] || $throughScopes !== [])) {
                throw new InvalidArgumentException('Relation scope attribute is allowed only on relation property: ' . $entityClass . '::$' . $propName);
            }

            if ($pivotScopes !== [] && !$hasBelongsToMany) {
                throw new InvalidArgumentException('PivotScope is allowed only on BelongsToMany relation property: ' . $entityClass . '::$' . $propName);
            }

            if ($throughScopes !== [] && !$hasHasManyThrough) {
                throw new InvalidArgumentException('ThroughScope is allowed only on HasManyThrough relation property: ' . $entityClass . '::$' . $propName);
            }

            if ($hasManyToOne && $hasBelongsTo) {
                throw new InvalidArgumentException('Relation property cannot have both #[ManyToOne] and #[BelongsTo]: ' . $entityClass . '::$' . $propName);
            }

            if (($belongsTo = $this->firstAttrInstance($prop, BelongsTo::class)) !== null) {
                /** @var BelongsTo $belongsTo */
                $joinColumn = $belongsTo->joinColumn;
                if ($joinColumn === null) {
                    $naming = $this->namingConvention;
                    if ($naming === null) {
                        throw new InvalidArgumentException('BelongsTo defaults require namingConvention.');
                    }
                    $joinColumn = $naming->belongsToJoinProperty($propName);
                }

                $relations[$propName] = new RelationMetadata(
                    property: $propName,
                    type: 'many_to_one',
                    targetEntity: $belongsTo->targetEntity,
                    joinColumn: $joinColumn,
                    referencedColumn: $belongsTo->referencedColumn,
                    relationScopes: $relationScopes,
                );
            }

            if (($manyToOne = $this->firstAttrInstance($prop, ManyToOne::class)) !== null) {
                /** @var ManyToOne $manyToOne */
                $joinColumn = $manyToOne->joinColumn;
                if ($joinColumn === null) {
                    $naming = $this->namingConvention;
                    if ($naming === null) {
                        throw new InvalidArgumentException('ManyToOne defaults require namingConvention.');
                    }
                    $joinColumn = $naming->belongsToJoinProperty($propName);
                }

                $relations[$propName] = new RelationMetadata(
                    property: $propName,
                    type: 'many_to_one',
                    targetEntity: $manyToOne->targetEntity,
                    joinColumn: $joinColumn,
                    referencedColumn: $manyToOne->referencedColumn,
                    relationScopes: $relationScopes,
                );
            }

            if (($hasOne = $this->firstAttrInstance($prop, HasOne::class)) !== null) {
                /** @var HasOne $hasOne */
                $foreignKey = $hasOne->foreignKey;
                if ($foreignKey === null) {
                    $naming = $this->namingConvention;
                    if ($naming === null) {
                        throw new InvalidArgumentException('HasOne defaults require namingConvention.');
                    }
                    $foreignKey = $naming->hasOneManyForeignKeyColumn($propName);
                }

                $relations[$propName] = new RelationMetadata(
                    property: $propName,
                    type: 'has_one',
                    targetEntity: $hasOne->targetEntity,
                    foreignKey: $foreignKey,
                    localKey: $hasOne->localKey,
                    relationScopes: $relationScopes,
                );
            }

            if (($hasMany = $this->firstAttrInstance($prop, HasMany::class)) !== null) {
                /** @var HasMany $hasMany */
                $foreignKey = $hasMany->foreignKey;
                if ($foreignKey === null) {
                    $naming = $this->namingConvention;
                    if ($naming === null) {
                        throw new InvalidArgumentException('HasMany defaults require namingConvention.');
                    }
                    $foreignKey = $naming->hasOneManyForeignKeyColumn($propName);
                }

                $relations[$propName] = new RelationMetadata(
                    property: $propName,
                    type: 'has_many',
                    targetEntity: $hasMany->targetEntity,
                    foreignKey: $foreignKey,
                    localKey: $hasMany->localKey,
                    relationScopes: $relationScopes,
                );
            }

            if (($btm = $this->firstAttrInstance($prop, BelongsToMany::class)) !== null) {
                /** @var BelongsToMany $btm */

                $pivotTable      = $btm->pivotTable;
                $foreignPivotKey = $btm->foreignPivotKey;
                $relatedPivotKey = $btm->relatedPivotKey;

                if ($pivotTable === null || $foreignPivotKey === null || $relatedPivotKey === null) {
                    $naming = $this->namingConvention;
                    if ($naming === null) {
                        throw new InvalidArgumentException('BelongsToMany defaults require namingConvention.');
                    }

                    $targetTable = $this->for($btm->targetEntity)->table;

                    if ($pivotTable === null && $btm->pivotOwner !== null) {
                        $ownerTable = $this->for($btm->pivotOwner)->table;

                        // (owner-first) pivotTable строим от owner + другой стороны.
                        // Если связь объявлена на owner-стороне, «другая сторона» — target.
                        // Если связь объявлена на обратной стороне, «другая сторона» — текущая table.
                        $otherTable = ($ownerTable === $table) ? $targetTable : $table;

                        $pivotTable = $naming->belongsToManyPivotTable($ownerTable, $otherTable);
                    }

                    $pivotTable ??= $naming->belongsToManyPivotTable($table, $targetTable);

                    // Ключи всегда считаются относительно «parent vs target».
                    // Для обратной стороны это автоматически означает swap: foreignPivotKey будет для текущей сущности,
                    // а relatedPivotKey — для targetEntity.
                    $foreignPivotKey ??= $naming->belongsToManyPivotKey($table);
                    $relatedPivotKey ??= $naming->belongsToManyPivotKey($targetTable);
                }

                $relations[$propName] = new RelationMetadata(
                    property: $propName,
                    type: 'belongs_to_many',
                    targetEntity: $btm->targetEntity,
                    pivotTable: $pivotTable,
                    foreignPivotKey: $foreignPivotKey,
                    relatedPivotKey: $relatedPivotKey,
                    parentKey: $btm->parentKey,
                    relatedKey: $btm->relatedKey,
                    pivotEntity: $btm->pivotEntity,
                    pivotAccessor: $btm->pivotAccessor,
                    relationScopes: $relationScopes,
                    pivotScopes: $pivotScopes,
                );
            }

            if (($hmt = $this->firstAttrInstance($prop, HasManyThrough::class)) !== null) {
                /** @var HasManyThrough $hmt */

                $firstKey  = $hmt->firstKey;
                $secondKey = $hmt->secondKey;

                if ($firstKey === null || $secondKey === null) {
                    $naming = $this->namingConvention;
                    if ($naming === null) {
                        throw new InvalidArgumentException('HasManyThrough defaults require namingConvention.');
                    }

                    $targetTable = $this->for($hmt->targetEntity)->table;

                    $firstKey ??= $naming->hasManyThroughFirstKey($table);
                    $secondKey ??= $naming->hasManyThroughSecondKey($targetTable);
                }

                $relations[$propName] = new RelationMetadata(
                    property: $propName,
                    type: 'has_many_through',
                    targetEntity: $hmt->targetEntity,
                    localKey: $hmt->localKey,
                    throughEntity: $hmt->throughEntity,
                    firstKey: $firstKey,
                    secondKey: $secondKey,
                    targetKey: $hmt->targetKey,
                    relationScopes: $relationScopes,
                    throughScopes: $throughScopes,
                );
            }

            if (($morphTo = $this->firstAttrInstance($prop, MorphTo::class)) !== null) {
                /** @var MorphTo $morphTo */
                $relations[$propName] = new RelationMetadata(
                    property: $propName,
                    type: 'morph_to',
                    targetEntity: 'object',
                    morphTypeColumn: $morphTo->typeColumn,
                    morphIdColumn: $morphTo->idColumn,
                    morphMap: $morphTo->map,
                    relationScopes: $relationScopes,
                );
            }

            if (($morphMany = $this->firstAttrInstance($prop, MorphMany::class)) !== null) {
                /** @var MorphMany $morphMany */
                $relations[$propName] = new RelationMetadata(
                    property: $propName,
                    type: 'morph_many',
                    targetEntity: $morphMany->targetEntity,
                    localKey: $morphMany->localKey,
                    morphTypeColumn: $morphMany->typeColumn,
                    morphIdColumn: $morphMany->idColumn,
                    morphTypeValue: $morphMany->typeValue,
                    relationScopes: $relationScopes,
                );
            }
        }

        $sluggables = [];
        foreach ($rc->getAttributes(Sluggable::class) as $attr) {
            /** @var Sluggable $slug */
            $slug         = $attr->newInstance();
            $sluggables[] = $slug;
        }

        $fullTextSearches = [];
        $defaultSearches  = 0;
        foreach ($rc->getAttributes(FullTextSearch::class) as $attr) {
            /** @var FullTextSearch $search */
            $search = $attr->newInstance();

            $name = trim($search->name);
            if ($name === '') {
                throw new InvalidArgumentException('FullTextSearch::name must be non-empty for ' . $entityClass . '.');
            }

            $vectorColumn = trim($search->vectorColumn);
            if ($vectorColumn === '') {
                throw new InvalidArgumentException('FullTextSearch::vectorColumn must be non-empty for ' . $entityClass . '.');
            }

            if (isset($fullTextSearches[$name])) {
                throw new InvalidArgumentException('Duplicate FullTextSearch profile "' . $name . '" for ' . $entityClass . '.');
            }

            if ($search->default) {
                $defaultSearches++;
            }

            $fullTextSearches[$name] = new FullTextSearchMetadata(
                name: $name,
                vectorColumn: $vectorColumn,
                config: $search->config,
                queryMode: $search->queryMode,
                rankFunction: $search->rankFunction,
                normalization: $search->normalization,
                default: $search->default,
            );
        }

        if ($defaultSearches > 1) {
            throw new InvalidArgumentException('Only one default FullTextSearch profile is allowed for ' . $entityClass . '.');
        }

        $changelogMeta = null;
        $changelogAttr = $this->firstAttrInstance($rc, Changelog::class);
        if ($changelogAttr instanceof Changelog) {
            $logHandler = $changelogAttr->logHandler !== null
                ? trim($changelogAttr->logHandler)
                : null;

            $changelogMeta = new ChangelogMetadata(
                enabled: $changelogAttr->enabled,
                logHandler: $logHandler !== '' ? $logHandler : null,
                ignore: $this->normalizeStringList($changelogAttr->ignore),
            );
        }

        return new ClassMetadata(
            class: $entityClass,
            table: $table,
            connection: $entityAttr->connection,
            repository: $entityAttr->repository,
            repositoryNamespace: $entityAttr->repositoryNamespace,
            pkProperties: $pkProperties,
            idGenerationStrategy: $idGenerationStrategy,
            columns: $columns,
            changelog: $changelogMeta,
            softDelete: $softDeleteMeta,
            sluggables: $sluggables,
            relations: $relations,
            fullTextSearches: $fullTextSearches,
            eventListeners: $eventListeners,
            hooks: $hooks,
        );
    }

    /**
     * @template T of object
     * @param ReflectionClass<object>|ReflectionProperty $ref
     * @param class-string<T> $attr
     * @return T|null
     */
    private function firstAttrInstance(ReflectionClass|ReflectionProperty $ref, string $attr): ?object
    {
        $attrs = $ref->getAttributes($attr);
        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * @param array<array-key, mixed> $values
     * @return list<string>
     */
    private function normalizeStringList(array $values): array
    {
        $result = [];
        $seen   = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $normalized = trim($value);
            if ($normalized === '') {
                continue;
            }

            if (array_key_exists($normalized, $seen)) {
                continue;
            }

            $result[]          = $normalized;
            $seen[$normalized] = true;
        }

        return $result;
    }

    /**
     * @param class-string $attr
     */
    private function hasAttr(ReflectionProperty $prop, string $attr): bool
    {
        return $prop->getAttributes($attr) !== [];
    }

    /**
     * @param class-string $attributeClass
     * @return list<class-string<RelationScopeInterface>>
     */
    private function relationScopeClasses(ReflectionProperty $prop, string $attributeClass): array
    {
        $classes = [];
        $seen    = [];
        foreach ($prop->getAttributes($attributeClass) as $attr) {
            $instance = $attr->newInstance();
            $scope    = is_string($instance->scope ?? null) ? trim($instance->scope) : '';
            if ($scope === '') {
                throw new InvalidArgumentException('Relation scope class must be non-empty for property: ' . $prop->getName());
            }

            if (!is_a($scope, RelationScopeInterface::class, true)) {
                throw new InvalidArgumentException('Relation scope class must implement ' . RelationScopeInterface::class . ': ' . $scope);
            }

            if (isset($seen[$scope])) {
                continue;
            }

            /** @var class-string<RelationScopeInterface> $scope */
            $classes[]    = $scope;
            $seen[$scope] = true;
        }

        return $classes;
    }
}
