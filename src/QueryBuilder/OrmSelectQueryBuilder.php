<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\QueryBuilder;

use InvalidArgumentException;
use PhpSoftBox\Database\Postgres\FullText\PgFullTextOptions;
use PhpSoftBox\Database\QueryBuilder\Expression;
use PhpSoftBox\Database\QueryBuilder\SelectQueryBuilder;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Metadata\FullTextSearchMetadata;
use PhpSoftBox\Orm\Metadata\RelationMetadata;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeInterface;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeQuery;
use PhpSoftBox\Orm\Result\EntityResult;
use PhpSoftBox\Pagination\Contracts\PaginationResultInterface;
use PhpSoftBox\Pagination\Paginator;

use function array_filter;
use function array_map;
use function array_merge;
use function array_slice;
use function array_values;
use function count;
use function implode;
use function is_string;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_contains;
use function strtolower;
use function strtoupper;
use function strtr;
use function trim;

/**
 * @template TEntity of EntityInterface
 * @mixin SelectQueryBuilder
 */
final class OrmSelectQueryBuilder
{
    private bool $softDeleteApplied = false;
    private string $softDeleteMode  = 'without';

    private ?string $rootAlias = null;
    private string $rootTable;

    private int $relationJoinCounter = 0;
    private bool $explicitSelect     = false;

    private int $relationSubqueryCounter = 0;

    /**
     * @var list<string>
     */
    private array $withRelations = [];

    private SelectQueryBuilder $query;
    private Paginator $paginator;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $entityClass,
        string $table,
        private readonly ?string $softDeleteColumn = null,
    ) {
        $this->query = new SelectQueryBuilder($entityManager->connection());

        $this->paginator = new Paginator();

        $this->rootTable = $table;
        $this->captureFrom($table);

        $this->query->from($table);
    }

    public function __call(string $name, array $arguments): mixed
    {
        $result = $this->query->{$name}(...$arguments);

        return $result instanceof SelectQueryBuilder ? $this : $result;
    }

    /**
     * @param list<string|Expression>|string|Expression $columns
     */
    public function select(array|string|Expression $columns): self
    {
        $this->explicitSelect = true;
        $this->query->select($columns);

        return $this;
    }

    public function selectRaw(string|Expression $expression, array $params = []): self
    {
        $this->explicitSelect = true;
        $this->query->selectRaw($expression, $params);

        return $this;
    }

    public function addSelectRaw(string|Expression $expression, array $params = []): self
    {
        return $this->selectRaw($expression, $params);
    }

    public function selectCount(string $column = '*', string $alias = 'count'): self
    {
        $this->ensureRootSelectionForComputedColumn();
        $this->query->selectCount($this->resolveRootPropertyColumnRef($column), $alias);

        return $this;
    }

    public function selectSum(string $column, string $alias): self
    {
        $this->ensureRootSelectionForComputedColumn();
        $this->query->selectSum($this->resolveRootPropertyColumnRef($column), $alias);

        return $this;
    }

    public function selectAvg(string $column, string $alias): self
    {
        $this->ensureRootSelectionForComputedColumn();
        $this->query->selectAvg($this->resolveRootPropertyColumnRef($column), $alias);

        return $this;
    }

    public function selectMin(string $column, string $alias): self
    {
        $this->ensureRootSelectionForComputedColumn();
        $this->query->selectMin($this->resolveRootPropertyColumnRef($column), $alias);

        return $this;
    }

    public function selectMax(string $column, string $alias): self
    {
        $this->ensureRootSelectionForComputedColumn();
        $this->query->selectMax($this->resolveRootPropertyColumnRef($column), $alias);

        return $this;
    }

    public function wherePgFullText(string $propertyOrColumn, string $query, ?PgFullTextOptions $options = null): self
    {
        $this->query->wherePgFullText($this->resolveRootPropertyColumnRef($propertyOrColumn), $query, $options);

        return $this;
    }

    public function orWherePgFullText(string $propertyOrColumn, string $query, ?PgFullTextOptions $options = null): self
    {
        $this->query->orWherePgFullText($this->resolveRootPropertyColumnRef($propertyOrColumn), $query, $options);

        return $this;
    }

    public function selectPgFullTextRank(
        string $propertyOrColumn,
        string $query,
        string $alias = 'search_rank',
        ?PgFullTextOptions $options = null,
    ): self {
        $this->ensureRootSelectionForComputedColumn();
        $this->query->selectPgFullTextRank($this->resolveRootPropertyColumnRef($propertyOrColumn), $query, $alias, $options);

        return $this;
    }

    public function selectPgFullTextHeadline(
        string $propertyOrColumn,
        string $query,
        string $alias = 'search_headline',
        ?PgFullTextOptions $options = null,
        ?string $headlineOptions = null,
    ): self {
        $this->ensureRootSelectionForComputedColumn();
        $this->query->selectPgFullTextHeadline(
            $this->resolveRootPropertyColumnRef($propertyOrColumn),
            $query,
            $alias,
            $options,
            $headlineOptions,
        );

        return $this;
    }

    public function orderByPgFullTextRank(string $alias = 'search_rank', string $direction = 'DESC'): self
    {
        $this->query->orderByPgFullTextRank($alias, $direction);

        return $this;
    }

    public function whereSearch(string $query, ?string $profile = null): self
    {
        $search = $this->resolveSearchProfile($profile);

        return $this->wherePgFullText($search->vectorColumn, $query, $this->searchOptions($search));
    }

    public function orWhereSearch(string $query, ?string $profile = null): self
    {
        $search = $this->resolveSearchProfile($profile);

        return $this->orWherePgFullText($search->vectorColumn, $query, $this->searchOptions($search));
    }

    public function selectSearchRank(string $query, string $alias = 'search_rank', ?string $profile = null): self
    {
        $search = $this->resolveSearchProfile($profile);

        return $this->selectPgFullTextRank($search->vectorColumn, $query, $alias, $this->searchOptions($search));
    }

    public function selectSearchHeadline(
        string $propertyOrColumn,
        string $query,
        string $alias = 'search_headline',
        ?string $profile = null,
        ?string $headlineOptions = null,
    ): self {
        $search = $this->resolveSearchProfile($profile);

        return $this->selectPgFullTextHeadline(
            $propertyOrColumn,
            $query,
            $alias,
            $this->searchOptions($search),
            $headlineOptions,
        );
    }

    /**
     * @param list<string> $profiles
     */
    public function whereAnySearch(string $query, array $profiles): self
    {
        $searches = [];
        foreach ($profiles as $profile) {
            $searches[] = $this->resolveSearchProfile($profile);
        }

        if ($searches === []) {
            return $this;
        }

        $this->query->where(function (SelectQueryBuilder $queryBuilder) use ($query, $searches): void {
            foreach ($searches as $index => $search) {
                $vector  = $this->resolveRootPropertyColumnRef($search->vectorColumn);
                $options = $this->searchOptions($search);
                if ($index === 0) {
                    $queryBuilder->wherePgFullText($vector, $query, $options);
                    continue;
                }

                $queryBuilder->orWherePgFullText($vector, $query, $options);
            }
        });

        return $this;
    }

    public function query(): SelectQueryBuilder
    {
        return $this->query;
    }

    public function from(string|Expression $table): self
    {
        if ($table instanceof Expression) {
            throw new InvalidArgumentException(
                'ORM queryFor(' . $this->entityClass . ') supports only the root entity table in from(). ' .
                'Use EntityManager::connection()->query()->select() for arbitrary tables.',
            );
        }

        $table = trim($table);
        if ($table === '') {
            return $this;
        }

        [$fromTable] = $this->splitTableAndAlias($table);
        if (!$this->isRootTableName($fromTable)) {
            throw new InvalidArgumentException(
                'ORM queryFor(' . $this->entityClass . ') cannot switch from "' . $this->rootTable . '" to "' . $fromTable . '". ' .
                'Use EntityManager::connection()->query()->select() for arbitrary tables.',
            );
        }

        $this->captureFrom($table);
        $this->query->from($table);

        return $this;
    }

    /**
     * Включает eager loading связей.
     *
     * @param list<string>|string $relations
     */
    public function with(array|string $relations): self
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        $this->withRelations = array_values(array_filter($relations, static fn (string $value): bool => $value !== ''));

        return $this;
    }

    /**
     * Добавляет в выборку счётчик связи как extra-колонку `<relation>_count`.
     *
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    public function withCount(string $relation, ?string $alias = null, ?callable $callback = null): self
    {
        [$relation, $alias] = $this->relationAndAlias($relation, $alias, 'count');

        $relationMeta = $this->relationMetadata($relation);
        if ($relationMeta->type === 'morph_to') {
            $this->ensureRootSelectionForComputedColumn();

            $compiled = $this->buildMorphToExistsExpression($relation, $relationMeta, $callback);

            $this->query->selectRaw(
                'CASE WHEN (' . $compiled['sql'] . ') THEN 1 ELSE 0 END AS ' . $alias,
                $compiled['params'],
            );

            return $this;
        }

        return $this->withAggregate($relation, 'COUNT', '*', $alias, $callback);
    }

    /**
     * Добавляет в выборку флаг наличия связанной записи как extra-колонку `<relation>_exists`.
     *
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    public function withExists(string $relation, ?string $alias = null, ?callable $callback = null): self
    {
        [$relation, $alias] = $this->relationAndAlias($relation, $alias, 'exists');

        $this->ensureRootSelectionForComputedColumn();

        $relationMeta = $this->relationMetadata($relation);
        if ($relationMeta->type === 'morph_to') {
            $compiled = $this->buildMorphToExistsExpression($relation, $relationMeta, $callback);

            $this->query->selectRaw('(' . $compiled['sql'] . ') AS ' . $alias, $compiled['params']);

            return $this;
        }

        $subquery = $this->buildRelationScalarSubquery(
            relation: $relation,
            selectExpression: '1',
            callback: $callback,
            relationMeta: $relationMeta,
        )->limit(1);

        $compiled = $subquery->toSql();

        $this->query->selectRaw('EXISTS (' . $compiled['sql'] . ') AS ' . $alias, $compiled['params']);

        return $this;
    }

    /**
     * Добавляет aggregate по связи как extra-колонку.
     *
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    public function withAggregate(
        string $relation,
        string $function,
        string $column = '*',
        ?string $alias = null,
        ?callable $callback = null,
    ): self {
        $function           = $this->normalizeAggregateFunction($function);
        [$relation, $alias] = $this->relationAndAlias($relation, $alias, strtolower($function), $column);

        $this->ensureRootSelectionForComputedColumn();

        $relationMeta = $this->relationMetadata($relation);
        if ($relationMeta->type === 'morph_to') {
            throw new InvalidArgumentException('Relation "' . $relation . '" of type morph_to is not supported for aggregate helpers except withExists() and withCount().');
        }

        $relatedAlias     = $this->nextRelationAlias('agg_' . $this->sanitizeAliasBase($relation) . '_');
        $selectExpression = $function . '(' . $this->relationAggregateColumnRef($relationMeta, $column, $relatedAlias) . ')';

        $subquery = $this->buildRelationScalarSubquery(
            relation: $relation,
            selectExpression: $selectExpression,
            callback: $callback,
            relationMeta: $relationMeta,
            relatedAlias: $relatedAlias,
        );

        $compiled = $subquery->toSql();

        $this->query->selectRaw('(' . $compiled['sql'] . ') AS ' . $alias, $compiled['params']);

        return $this;
    }

    /**
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    public function withSum(string $relation, string $column, ?string $alias = null, ?callable $callback = null): self
    {
        return $this->withAggregate($relation, 'SUM', $column, $alias, $callback);
    }

    /**
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    public function withAvg(string $relation, string $column, ?string $alias = null, ?callable $callback = null): self
    {
        return $this->withAggregate($relation, 'AVG', $column, $alias, $callback);
    }

    /**
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    public function withMin(string $relation, string $column, ?string $alias = null, ?callable $callback = null): self
    {
        return $this->withAggregate($relation, 'MIN', $column, $alias, $callback);
    }

    /**
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    public function withMax(string $relation, string $column, ?string $alias = null, ?callable $callback = null): self
    {
        return $this->withAggregate($relation, 'MAX', $column, $alias, $callback);
    }

    /**
     * Снимает soft delete фильтр (включая удалённые записи).
     */
    public function withDeleted(): self
    {
        $this->softDeleteMode = 'with';

        return $this;
    }

    /**
     * Ограничивает выборку только удалёнными записями.
     */
    public function onlyDeleted(): self
    {
        $this->softDeleteMode = 'only';

        return $this;
    }

    /**
     * Добавляет условие наличия связанной записи.
     *
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    public function whereHas(string $relation, ?callable $callback = null): self
    {
        return $this->addRelationExistenceCondition($relation, $callback, boolean: 'and', not: false);
    }

    /** @param callable(OrmRelationQueryBuilder):void|null $callback */
    public function orWhereHas(string $relation, ?callable $callback = null): self
    {
        return $this->addRelationExistenceCondition($relation, $callback, boolean: 'or', not: false);
    }

    /** @param callable(OrmRelationQueryBuilder):void|null $callback */
    public function whereDoesntHave(string $relation, ?callable $callback = null): self
    {
        return $this->addRelationExistenceCondition($relation, $callback, boolean: 'and', not: true);
    }

    /** @param callable(OrmRelationQueryBuilder):void|null $callback */
    public function orWhereDoesntHave(string $relation, ?callable $callback = null): self
    {
        return $this->addRelationExistenceCondition($relation, $callback, boolean: 'or', not: true);
    }

    public function whereRelation(
        string $relation,
        string $propertyOrColumn,
        string $operator,
        mixed $value,
    ): self {
        return $this->whereHas(
            $relation,
            static fn (OrmRelationQueryBuilder $query): OrmRelationQueryBuilder => $query->whereProperty(
                $propertyOrColumn,
                $operator,
                $value,
            ),
        );
    }

    /**
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    private function addRelationExistenceCondition(
        string $path,
        ?callable $callback,
        string $boolean,
        bool $not,
    ): self {
        $path = trim($path);
        if ($path === '') {
            throw new InvalidArgumentException('Relation path must be non-empty.');
        }

        $segments = preg_split('/\./', $path);
        if ($segments === false || $segments === [] || array_filter($segments, static fn (string $part): bool => trim($part) === '') !== []) {
            throw new InvalidArgumentException('Invalid relation path: ' . $path);
        }

        $segments = array_values(array_map(trim(...), $segments));

        $subquery = function (SelectQueryBuilder $query) use ($callback, $path, $segments): void {
            $this->buildRelationPathExists(
                query: $query,
                sourceEntity: $this->entityClass,
                sourceRef: $this->rootRef(),
                segments: $segments,
                fullPath: $path,
                callback: $callback,
            );
        };

        $method = match ([$boolean, $not]) {
            ['or', false] => 'orWhereExists',
            ['and', true] => 'whereNotExists',
            ['or', true]  => 'orWhereNotExists',
            default       => 'whereExists',
        };

        $this->query->{$method}($subquery);

        return $this;
    }

    /**
     * @param class-string $sourceEntity
     * @param non-empty-list<string> $segments
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    private function buildRelationPathExists(
        SelectQueryBuilder $query,
        string $sourceEntity,
        string $sourceRef,
        array $segments,
        string $fullPath,
        ?callable $callback,
    ): void {
        $relationName = $segments[0];
        $remaining    = array_slice($segments, 1);
        $sourceMeta   = $this->entityManager->metadataProvider()->for($sourceEntity);
        $relation     = $sourceMeta->relations[$relationName] ?? null;

        if (!$relation instanceof RelationMetadata) {
            throw new InvalidArgumentException(
                'Invalid relation path "' . $fullPath . '": relation "' . $relationName . '" not found on ' . $sourceEntity . '.',
            );
        }

        if ($relation->type === 'morph_to') {
            $this->buildMorphToPathExists($query, $sourceEntity, $sourceRef, $relationName, $relation, $remaining, $fullPath, $callback);

            return;
        }

        $targetEntity = $relation->targetEntity;
        $targetMeta   = $this->entityManager->metadataProvider()->for($targetEntity);
        $targetAlias  = $this->nextRelationAlias('rel_' . $this->sanitizeAliasBase($relationName) . '_');
        $pivotAlias   = null;

        $query->select('1');

        if ($relation->type === 'has_many' || $relation->type === 'has_one') {
            if ($relation->foreignKey === null) {
                throw new InvalidArgumentException('Relation "' . $relationName . '" is missing foreign key.');
            }

            $foreignKey = $this->resolveEntityColumn($targetEntity, $relation->foreignKey);
            $localKey   = $this->resolveEntityColumn($sourceEntity, $relation->localKey);

            $query
                ->from($targetMeta->table . ' ' . $targetAlias)
                ->where($targetAlias . '.' . $foreignKey . ' = ' . $sourceRef . '.' . $localKey);
        } elseif ($relation->type === 'many_to_one') {
            if ($relation->joinColumn === null) {
                throw new InvalidArgumentException('Relation "' . $relationName . '" is missing join column.');
            }

            $joinColumn       = $this->resolveEntityColumn($sourceEntity, $relation->joinColumn);
            $referencedColumn = $this->resolveEntityColumn($targetEntity, $relation->referencedColumn);

            $query
                ->from($targetMeta->table . ' ' . $targetAlias)
                ->where($targetAlias . '.' . $referencedColumn . ' = ' . $sourceRef . '.' . $joinColumn);
        } elseif ($relation->type === 'belongs_to_many') {
            if ($relation->pivotTable === null || $relation->foreignPivotKey === null || $relation->relatedPivotKey === null) {
                throw new InvalidArgumentException('BelongsToMany relation "' . $relationName . '" is missing pivot metadata.');
            }

            $pivotAlias = $this->nextRelationAlias('pivot_' . $this->sanitizeAliasBase($relationName) . '_');
            $parentKey  = $this->resolveEntityColumn($sourceEntity, $relation->parentKey);
            $relatedKey = $this->resolveEntityColumn($targetEntity, $relation->relatedKey);

            $query
                ->from($relation->pivotTable . ' ' . $pivotAlias)
                ->innerJoin(
                    $targetMeta->table . ' ' . $targetAlias,
                    $targetAlias . '.' . $relatedKey . ' = ' . $pivotAlias . '.' . $relation->relatedPivotKey,
                )
                ->where($pivotAlias . '.' . $relation->foreignPivotKey . ' = ' . $sourceRef . '.' . $parentKey);

            $this->applyRelationScopes($query, $relation->pivotScopes, $pivotAlias);
            if ($relation->pivotEntity !== null) {
                $this->applyTargetSoftDeleteScope($query, $relation->pivotEntity, $pivotAlias);
            }
        } elseif ($relation->type === 'has_many_through') {
            if ($relation->throughEntity === null || $relation->firstKey === null || $relation->secondKey === null) {
                throw new InvalidArgumentException('HasManyThrough relation "' . $relationName . '" is missing keys.');
            }

            $pivotAlias  = $this->nextRelationAlias('through_' . $this->sanitizeAliasBase($relationName) . '_');
            $throughMeta = $this->entityManager->metadataProvider()->for($relation->throughEntity);
            $localKey    = $this->resolveEntityColumn($sourceEntity, $relation->localKey);
            $targetKey   = $this->resolveEntityColumn($targetEntity, $relation->targetKey);

            $query
                ->from($throughMeta->table . ' ' . $pivotAlias)
                ->innerJoin(
                    $targetMeta->table . ' ' . $targetAlias,
                    $targetAlias . '.' . $targetKey . ' = ' . $pivotAlias . '.' . $relation->secondKey,
                )
                ->where($pivotAlias . '.' . $relation->firstKey . ' = ' . $sourceRef . '.' . $localKey);

            $this->applyRelationScopes($query, $relation->throughScopes, $pivotAlias);
            $this->applyTargetSoftDeleteScope($query, $relation->throughEntity, $pivotAlias);
        } elseif ($relation->type === 'morph_many') {
            if ($relation->morphTypeColumn === null || $relation->morphIdColumn === null || $relation->morphTypeValue === null) {
                throw new InvalidArgumentException('MorphMany relation "' . $relationName . '" is missing morph configuration.');
            }

            $localKey      = $this->resolveEntityColumn($sourceEntity, $relation->localKey);
            $morphIdColumn = $this->resolveEntityColumn($targetEntity, $relation->morphIdColumn);
            $morphType     = $this->resolveEntityColumn($targetEntity, $relation->morphTypeColumn);
            $typeParam     = '__orm_relation_morph_type_' . (++$this->relationSubqueryCounter);

            $query
                ->from($targetMeta->table . ' ' . $targetAlias)
                ->where($targetAlias . '.' . $morphIdColumn . ' = ' . $sourceRef . '.' . $localKey)
                ->where($targetAlias . '.' . $morphType . ' = :' . $typeParam, [$typeParam => $relation->morphTypeValue]);
        } else {
            throw new InvalidArgumentException('Relation type "' . $relation->type . '" is not supported for whereHas().');
        }

        $this->applyTargetSoftDeleteScope($query, $targetEntity, $targetAlias);
        $this->applyRelationScopes($query, $relation->relationScopes, $targetAlias);

        if ($remaining !== []) {
            $query->whereExists(function (SelectQueryBuilder $nested) use ($callback, $fullPath, $remaining, $targetAlias, $targetEntity): void {
                $this->buildRelationPathExists($nested, $targetEntity, $targetAlias, $remaining, $fullPath, $callback);
            });

            return;
        }

        if ($callback !== null) {
            $callback(new OrmRelationQueryBuilder($query, $targetAlias, $pivotAlias, $this->entityManager, $targetEntity));
        }
    }

    /**
     * @param class-string $sourceEntity
     * @param list<string> $remaining
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    private function buildMorphToPathExists(
        SelectQueryBuilder $query,
        string $sourceEntity,
        string $sourceRef,
        string $relationName,
        RelationMetadata $relation,
        array $remaining,
        string $fullPath,
        ?callable $callback,
    ): void {
        if ($relation->morphTypeColumn === null || $relation->morphIdColumn === null || $relation->morphMap === []) {
            throw new InvalidArgumentException('MorphTo relation "' . $relationName . '" has incomplete morph metadata.');
        }

        $query->select('1')->where(function (SelectQueryBuilder $group) use (
            $callback,
            $fullPath,
            $relation,
            $relationName,
            $remaining,
            $sourceEntity,
            $sourceRef,
        ): void {
            $first = true;
            foreach ($relation->morphMap as $typeValue => $targetEntity) {
                $method = $first ? 'whereExists' : 'orWhereExists';
                $first  = false;

                $group->{$method}(function (SelectQueryBuilder $targetQuery) use (
                    $callback,
                    $fullPath,
                    $relation,
                    $relationName,
                    $remaining,
                    $sourceEntity,
                    $sourceRef,
                    $targetEntity,
                    $typeValue,
                ): void {
                    $targetMeta  = $this->entityManager->metadataProvider()->for($targetEntity);
                    $targetAlias = $this->nextRelationAlias('morph_' . $this->sanitizeAliasBase($relationName) . '_');
                    $pkProperty  = $targetMeta->pkProperties[0] ?? 'id';
                    $pkColumn    = $this->resolveEntityColumn($targetEntity, $pkProperty);
                    $morphId     = $this->resolveEntityColumn($sourceEntity, $relation->morphIdColumn);
                    $morphType   = $this->resolveEntityColumn($sourceEntity, $relation->morphTypeColumn);
                    $typeParam   = '__orm_relation_morph_type_' . (++$this->relationSubqueryCounter);

                    $targetQuery
                        ->select('1')
                        ->from($targetMeta->table . ' ' . $targetAlias)
                        ->where($targetAlias . '.' . $pkColumn . ' = ' . $sourceRef . '.' . $morphId)
                        ->where($sourceRef . '.' . $morphType . ' = :' . $typeParam, [$typeParam => $typeValue]);

                    $this->applyTargetSoftDeleteScope($targetQuery, $targetEntity, $targetAlias);
                    $this->applyRelationScopes($targetQuery, $relation->relationScopes, $targetAlias);

                    if ($remaining !== []) {
                        $targetQuery->whereExists(function (SelectQueryBuilder $nested) use ($callback, $fullPath, $remaining, $targetAlias, $targetEntity): void {
                            $this->buildRelationPathExists($nested, $targetEntity, $targetAlias, $remaining, $fullPath, $callback);
                        });
                    } elseif ($callback !== null) {
                        $callback(new OrmRelationQueryBuilder($targetQuery, $targetAlias, null, $this->entityManager, $targetEntity));
                    }
                });
            }
        });
    }

    /**
     * @return EntityCollection<TEntity>
     */
    public function fetchEntities(): EntityCollection
    {
        $this->ensureRootSelection();
        $this->applySoftDeleteScope();

        $rows = $this->query->fetchAll();

        $entities = $this->hydrateRows($rows);

        if ($this->withRelations !== []) {
            $this->entityManager->load($entities, $this->withRelations);
        }

        return $entities;
    }

    /**
     * @return TEntity|null
     */
    public function fetchEntity(): ?EntityInterface
    {
        $this->ensureRootSelection();
        $this->applySoftDeleteScope();

        $row = $this->query->fetchOne();
        if ($row === null) {
            return null;
        }

        $entities = $this->hydrateRows([$row]);

        /** @var TEntity|null $entity */
        $entity = $entities->first();
        if ($entity !== null && $this->withRelations !== []) {
            $this->entityManager->load($entity, $this->withRelations);
        }

        return $entity;
    }

    /**
     * Возвращает сущности вместе с вычисленными колонками запроса.
     *
     * @return list<EntityResult<TEntity>>
     */
    public function fetchEntityResults(): array
    {
        $this->ensureRootSelection();
        $this->applySoftDeleteScope();

        $rows = $this->query->fetchAll();

        $entities = $this->hydrateRows($rows);
        if ($this->withRelations !== []) {
            $this->entityManager->load($entities, $this->withRelations);
        }

        return $this->makeEntityResults($entities->all(), $rows);
    }

    public function paginateEntities(?int $page = null, ?int $perPage = null): PaginationResultInterface
    {
        $this->ensureRootSelection();
        $this->applySoftDeleteScope();

        $pagination = $this->query->paginate($page, $perPage);
        $meta       = $pagination->meta();

        $entities = $this->hydrateRows($pagination->data());
        if ($this->withRelations !== []) {
            $this->entityManager->load($entities, $this->withRelations);
        }

        return $this->paginator->make(
            items: $entities->all(),
            total: (int) ($meta['total'] ?? 0),
            page: (int) ($meta['current_page'] ?? 1),
            perPage: (int) ($meta['per_page'] ?? 1),
        );
    }

    public function paginateEntityResults(?int $page = null, ?int $perPage = null): PaginationResultInterface
    {
        $this->ensureRootSelection();
        $this->applySoftDeleteScope();

        $pagination = $this->query->paginate($page, $perPage);
        $meta       = $pagination->meta();
        $rows       = $pagination->data();

        $entities = $this->hydrateRows($rows);
        if ($this->withRelations !== []) {
            $this->entityManager->load($entities, $this->withRelations);
        }

        return $this->paginator->make(
            items: $this->makeEntityResults($entities->all(), $rows),
            total: (int) ($meta['total'] ?? 0),
            page: (int) ($meta['current_page'] ?? 1),
            perPage: (int) ($meta['per_page'] ?? 1),
        );
    }

    public function fetchAll(): array
    {
        $this->applySoftDeleteScope();

        return $this->query->fetchAll();
    }

    public function fetchOne(): ?array
    {
        $this->applySoftDeleteScope();

        return $this->query->fetchOne();
    }

    public function first(): ?array
    {
        $this->applySoftDeleteScope();

        return $this->query->first();
    }

    public function value(string $column): mixed
    {
        $this->applySoftDeleteScope();

        return $this->query->value($column);
    }

    public function count(string $column = '*'): int
    {
        $this->applySoftDeleteScope();

        return $this->query->count($column);
    }

    public function exists(): bool
    {
        $this->applySoftDeleteScope();

        return $this->query->exists();
    }

    public function sum(string $column): float|int
    {
        $this->applySoftDeleteScope();

        return $this->query->sum($column);
    }

    public function avg(string $column): float
    {
        $this->applySoftDeleteScope();

        return $this->query->avg($column);
    }

    public function min(string $column): mixed
    {
        $this->applySoftDeleteScope();

        return $this->query->min($column);
    }

    public function max(string $column): mixed
    {
        $this->applySoftDeleteScope();

        return $this->query->max($column);
    }

    public function paginate(?int $page = null, ?int $perPage = null): PaginationResultInterface
    {
        $this->applySoftDeleteScope();

        return $this->query->paginate($page, $perPage);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return EntityCollection<TEntity>
     */
    private function hydrateRows(array $rows): EntityCollection
    {
        $entities = $this->entityManager->bulkRepository($this->entityClass)->hydrateManyRows($rows);

        return $this->entityManager->manageHydratedEntities($entities);
    }

    /**
     * @param list<EntityInterface> $entities
     * @param list<array<string, mixed>> $rows
     * @return list<EntityResult<TEntity>>
     */
    private function makeEntityResults(array $entities, array $rows): array
    {
        $results = [];
        foreach ($entities as $index => $entity) {
            $results[] = new EntityResult(
                entity: $entity,
                extra: $this->extraColumnsFromRow($rows[$index] ?? []),
            );
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function extraColumnsFromRow(array $row): array
    {
        foreach ($this->entityHydrationKeys() as $key => $_) {
            unset($row[$key]);
        }

        return $row;
    }

    /**
     * @return array<string, true>
     */
    private function entityHydrationKeys(): array
    {
        $meta = $this->entityManager->metadataProvider()->for($this->entityClass);
        $keys = [];

        foreach ($meta->columns as $property => $column) {
            $keys[$property]       = true;
            $keys[$column->column] = true;
        }

        return $keys;
    }

    /**
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     */
    private function buildRelationScalarSubquery(
        string $relation,
        string $selectExpression,
        ?callable $callback = null,
        ?RelationMetadata $relationMeta = null,
        ?string $relatedAlias = null,
    ): SelectQueryBuilder {
        $relationMeta ??= $this->relationMetadata($relation);
        $relatedAlias ??= $this->nextRelationAlias('sub_' . $this->sanitizeAliasBase($relation) . '_');

        if ($relationMeta->type === 'morph_to') {
            throw new InvalidArgumentException('Relation "' . $relation . '" of type morph_to is not supported for with* aggregate helpers.');
        }

        $subquery   = new SelectQueryBuilder($this->entityManager->connection());
        $pivotAlias = null;

        $subquery->selectRaw($selectExpression);

        if ($relationMeta->type === 'has_many' || $relationMeta->type === 'has_one') {
            if ($relationMeta->foreignKey === null) {
                throw new InvalidArgumentException('Relation "' . $relation . '" is missing foreign key.');
            }

            $targetTable = $this->entityManager->metadataProvider()->for($relationMeta->targetEntity)->table;

            $subquery
                ->from($targetTable . ' ' . $relatedAlias)
                ->where($relatedAlias . '.' . $relationMeta->foreignKey . ' = ' . $this->rootColumnRef($relationMeta->localKey));

            $this->applyTargetSoftDeleteScope($subquery, $relationMeta->targetEntity, $relatedAlias);
            $this->applyRelationScopes($subquery, $relationMeta->relationScopes, $relatedAlias);
        } elseif ($relationMeta->type === 'many_to_one') {
            if ($relationMeta->joinColumn === null) {
                throw new InvalidArgumentException('Relation "' . $relation . '" is missing join column.');
            }

            $targetTable      = $this->entityManager->metadataProvider()->for($relationMeta->targetEntity)->table;
            $referencedColumn = $this->resolveEntityColumn($relationMeta->targetEntity, $relationMeta->referencedColumn);

            $subquery
                ->from($targetTable . ' ' . $relatedAlias)
                ->where($relatedAlias . '.' . $referencedColumn . ' = ' . $this->rootColumnRef($relationMeta->joinColumn));

            $this->applyTargetSoftDeleteScope($subquery, $relationMeta->targetEntity, $relatedAlias);
            $this->applyRelationScopes($subquery, $relationMeta->relationScopes, $relatedAlias);
        } elseif ($relationMeta->type === 'belongs_to_many') {
            if ($relationMeta->pivotTable === null
                || $relationMeta->foreignPivotKey === null
                || $relationMeta->relatedPivotKey === null
            ) {
                throw new InvalidArgumentException('BelongsToMany relation "' . $relation . '" is missing pivot metadata.');
            }

            $pivotAlias  = $this->nextRelationAlias('pivot_' . $this->sanitizeAliasBase($relation) . '_');
            $targetTable = $this->entityManager->metadataProvider()->for($relationMeta->targetEntity)->table;
            $relatedKey  = $this->resolveEntityColumn($relationMeta->targetEntity, $relationMeta->relatedKey);

            $subquery
                ->from($relationMeta->pivotTable . ' ' . $pivotAlias)
                ->innerJoin(
                    $targetTable . ' ' . $relatedAlias,
                    $relatedAlias . '.' . $relatedKey . ' = ' . $pivotAlias . '.' . $relationMeta->relatedPivotKey,
                )
                ->where($pivotAlias . '.' . $relationMeta->foreignPivotKey . ' = ' . $this->rootColumnRef($relationMeta->parentKey));

            $this->applyRelationScopes($subquery, $relationMeta->pivotScopes, $pivotAlias);
            $this->applyTargetSoftDeleteScope($subquery, $relationMeta->targetEntity, $relatedAlias);
            $this->applyRelationScopes($subquery, $relationMeta->relationScopes, $relatedAlias);
        } elseif ($relationMeta->type === 'has_many_through') {
            if ($relationMeta->throughEntity === null
                || $relationMeta->firstKey === null
                || $relationMeta->secondKey === null
            ) {
                throw new InvalidArgumentException('HasManyThrough relation "' . $relation . '" is missing keys.');
            }

            $pivotAlias   = $this->nextRelationAlias('through_' . $this->sanitizeAliasBase($relation) . '_');
            $throughTable = $this->entityManager->metadataProvider()->for($relationMeta->throughEntity)->table;
            $targetTable  = $this->entityManager->metadataProvider()->for($relationMeta->targetEntity)->table;
            $targetKey    = $this->resolveEntityColumn($relationMeta->targetEntity, $relationMeta->targetKey);

            $subquery
                ->from($throughTable . ' ' . $pivotAlias)
                ->innerJoin(
                    $targetTable . ' ' . $relatedAlias,
                    $relatedAlias . '.' . $targetKey . ' = ' . $pivotAlias . '.' . $relationMeta->secondKey,
                )
                ->where($pivotAlias . '.' . $relationMeta->firstKey . ' = ' . $this->rootColumnRef($relationMeta->localKey));

            $this->applyRelationScopes($subquery, $relationMeta->throughScopes, $pivotAlias);
            $this->applyTargetSoftDeleteScope($subquery, $relationMeta->targetEntity, $relatedAlias);
            $this->applyRelationScopes($subquery, $relationMeta->relationScopes, $relatedAlias);
        } elseif ($relationMeta->type === 'morph_many') {
            if ($relationMeta->morphTypeColumn === null
                || $relationMeta->morphIdColumn === null
                || $relationMeta->morphTypeValue === null
            ) {
                throw new InvalidArgumentException('MorphMany relation "' . $relation . '" is missing morph configuration.');
            }

            $targetTable = $this->entityManager->metadataProvider()->for($relationMeta->targetEntity)->table;
            $typeParam   = '__orm_relation_morph_type_' . (++$this->relationSubqueryCounter);

            $subquery
                ->from($targetTable . ' ' . $relatedAlias)
                ->where($relatedAlias . '.' . $relationMeta->morphIdColumn . ' = ' . $this->rootColumnRef($relationMeta->localKey))
                ->where(
                    $relatedAlias . '.' . $relationMeta->morphTypeColumn . ' = :' . $typeParam,
                    [$typeParam => $relationMeta->morphTypeValue],
                );

            $this->applyTargetSoftDeleteScope($subquery, $relationMeta->targetEntity, $relatedAlias);
            $this->applyRelationScopes($subquery, $relationMeta->relationScopes, $relatedAlias);
        } else {
            throw new InvalidArgumentException('Relation type "' . $relationMeta->type . '" is not supported for with* aggregate helpers.');
        }

        if ($callback !== null) {
            $callback(new OrmRelationQueryBuilder(
                $subquery,
                $relatedAlias,
                $pivotAlias,
                $this->entityManager,
                $relationMeta->targetEntity,
            ));
        }

        return $subquery;
    }

    /**
     * @param callable(OrmRelationQueryBuilder):void|null $callback
     * @return array{sql: string, params: array<string|int, mixed>}
     */
    private function buildMorphToExistsExpression(string $relation, RelationMetadata $relationMeta, ?callable $callback = null): array
    {
        if ($relationMeta->morphTypeColumn === null || $relationMeta->morphIdColumn === null) {
            throw new InvalidArgumentException('MorphTo relation "' . $relation . '" is missing morph columns.');
        }

        if ($relationMeta->morphMap === []) {
            throw new InvalidArgumentException('MorphTo relation "' . $relation . '" has empty morph map.');
        }

        $parts  = [];
        $params = [];

        foreach ($relationMeta->morphMap as $typeValue => $targetClass) {
            if (!is_string($targetClass) || $targetClass === '') {
                continue;
            }

            $targetMeta  = $this->entityManager->metadataProvider()->for($targetClass);
            $targetTable = $targetMeta->table;
            $pkProperty  = $targetMeta->pkProperties[0] ?? 'id';
            $pkColumn    = $targetMeta->columns[$pkProperty]->column ?? $pkProperty;

            $targetAlias = $this->nextRelationAlias('morph_' . $this->sanitizeAliasBase($relation) . '_');
            $typeParam   = '__orm_morph_type';
            $subquery    = new SelectQueryBuilder($this->entityManager->connection());

            $subquery
                ->select('1')
                ->from($targetTable . ' ' . $targetAlias)
                ->where($targetAlias . '.' . $pkColumn . ' = ' . $this->rootColumnRef($relationMeta->morphIdColumn))
                ->where(
                    $this->rootColumnRef($relationMeta->morphTypeColumn) . ' = :' . $typeParam,
                    [$typeParam => $typeValue],
                )
                ->limit(1);

            $this->applyTargetSoftDeleteScope($subquery, $targetClass, $targetAlias);
            $this->applyRelationScopes($subquery, $relationMeta->relationScopes, $targetAlias);

            if ($callback !== null) {
                $callback(new OrmRelationQueryBuilder(
                    $subquery,
                    $targetAlias,
                    null,
                    $this->entityManager,
                    $targetClass,
                ));
            }

            $compiled = $this->prefixCompiledParams(
                $subquery->toSql(),
                '__orm_morph_' . (++$this->relationSubqueryCounter) . '_',
            );

            $parts[] = 'EXISTS (' . $compiled['sql'] . ')';
            $params  = array_merge($params, $compiled['params']);
        }

        if ($parts === []) {
            throw new InvalidArgumentException('MorphTo relation "' . $relation . '" has no valid targets.');
        }

        return [
            'sql'    => implode(' OR ', $parts),
            'params' => $params,
        ];
    }

    /**
     * @param array{sql: string, params: array<string|int, mixed>} $compiled
     * @return array{sql: string, params: array<string|int, mixed>}
     */
    private function prefixCompiledParams(array $compiled, string $prefix): array
    {
        if ($compiled['params'] === []) {
            return $compiled;
        }

        $replacements = [];
        $params       = [];

        foreach ($compiled['params'] as $key => $value) {
            $keyString                      = (string) $key;
            $prefixedKey                    = $prefix . $keyString;
            $replacements[':' . $keyString] = ':' . $prefixedKey;
            $params[$prefixedKey]           = $value;
        }

        return [
            'sql'    => strtr($compiled['sql'], $replacements),
            'params' => $params,
        ];
    }

    private function relationMetadata(string $relation): RelationMetadata
    {
        $relation = trim($relation);
        if ($relation === '') {
            throw new InvalidArgumentException('Relation name must be non-empty.');
        }

        $meta         = $this->entityManager->metadataProvider()->for($this->entityClass);
        $relationMeta = $meta->relations[$relation] ?? null;
        if (!$relationMeta instanceof RelationMetadata) {
            throw new InvalidArgumentException('Relation not found: ' . $relation);
        }

        return $relationMeta;
    }

    private function relationAggregateColumnRef(RelationMetadata $relation, string $column, string $relatedAlias): string
    {
        $column = trim($column);
        if ($column === '' || $column === '*') {
            return '*';
        }

        if (str_contains($column, '.')) {
            return $column;
        }

        return $relatedAlias . '.' . $this->resolveEntityColumn($relation->targetEntity, $column);
    }

    private function normalizeAggregateFunction(string $function): string
    {
        $function = strtoupper(trim($function));

        return match ($function) {
            'COUNT', 'SUM', 'AVG', 'MIN', 'MAX' => $function,
            default                             => throw new InvalidArgumentException('Unsupported aggregate function: ' . $function),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function relationAndAlias(string $relation, ?string $alias, string $suffix, ?string $column = null): array
    {
        $relation = trim($relation);
        if ($relation === '') {
            throw new InvalidArgumentException('Relation name must be non-empty.');
        }

        if (preg_match('/^(.+?)\s+as\s+([a-z_][a-z0-9_]*)$/i', $relation, $matches) === 1) {
            if ($alias !== null && trim($alias) !== '') {
                throw new InvalidArgumentException('Relation alias cannot be passed twice.');
            }

            $relation = trim((string) $matches[1]);
            $alias    = trim((string) $matches[2]);
        }

        $alias = $alias !== null && trim($alias) !== ''
            ? trim($alias)
            : $this->defaultRelationAggregateAlias($relation, $suffix, $column);

        $this->assertSimpleAlias($alias);

        return [$relation, $alias];
    }

    private function defaultRelationAggregateAlias(string $relation, string $suffix, ?string $column = null): string
    {
        $base = $this->snake($relation);
        if ($column !== null && trim($column) !== '' && trim($column) !== '*') {
            return $base . '_' . $this->snake($column) . '_' . $suffix;
        }

        return $base . '_' . $suffix;
    }

    private function assertSimpleAlias(string $alias): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]*$/i', $alias) === 1) {
            return;
        }

        throw new InvalidArgumentException('Computed relation alias must be a simple SQL identifier: ' . $alias);
    }

    private function rootColumnRef(string $propertyOrColumn): string
    {
        return $this->rootRef() . '.' . $this->resolveEntityColumn($this->entityClass, $propertyOrColumn);
    }

    private function applyTargetSoftDeleteScope(SelectQueryBuilder $query, string $entityClass, string $alias): void
    {
        $softDeleteColumn = $this->entityManager->metadataProvider()->for($entityClass)->softDelete?->column;
        if ($softDeleteColumn === null) {
            return;
        }

        $query->whereNull($alias . '.' . $softDeleteColumn);
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
            $this->entityManager->relationScopeResolver()->resolve($scopeClass)->apply($scopeQuery);
        }
    }

    private function snake(string $value): string
    {
        $snake = preg_replace('~(?<=\\w)([A-Z])~u', '_$1', $value);

        if ($snake === null) {
            return $value;
        }

        return strtolower($snake);
    }

    private function applySoftDeleteScope(): void
    {
        if ($this->softDeleteApplied) {
            return;
        }

        $this->softDeleteApplied = true;

        if ($this->softDeleteColumn === null) {
            return;
        }

        if ($this->softDeleteMode === 'with') {
            return;
        }

        $column = $this->softDeleteColumn;
        if (!str_contains($column, '.')) {
            $column = $this->rootRef() . '.' . $column;
        }

        if ($this->softDeleteMode === 'only') {
            $this->query->where($column . ' IS NOT NULL');

            return;
        }

        $this->query->where($column . ' IS NULL');
    }

    private function captureFrom(string $table): void
    {
        $table = trim($table);
        if ($table === '') {
            return;
        }

        [, $alias]       = $this->splitTableAndAlias($table);
        $this->rootAlias = $alias;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitTableAndAlias(string $table): array
    {
        $parts = preg_split('/\s+/', $table) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $value): bool => $value !== ''));

        return [$parts[0] ?? '', $parts[1] ?? null];
    }

    private function isRootTableName(string $tableName): bool
    {
        if ($tableName === '') {
            return false;
        }

        if ($tableName === $this->rootTable) {
            return true;
        }

        return $tableName === $this->entityManager->connection()->table($this->rootTable);
    }

    private function rootRef(): string
    {
        if ($this->rootAlias !== null) {
            return $this->rootAlias;
        }

        return $this->entityManager->connection()->table($this->rootTable);
    }

    private function nextRelationAlias(string $prefix): string
    {
        $this->relationJoinCounter++;

        return $prefix . $this->relationJoinCounter;
    }

    private function sanitizeAliasBase(string $value): string
    {
        $value = preg_replace('~(?<=\\w)([A-Z])~u', '_$1', $value) ?? 'rel';
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? 'rel';
        $value = preg_replace('/_+/', '_', $value) ?? 'rel';
        $value = trim($value, '_');

        return $value !== '' ? $value : 'rel';
    }

    /**
     * Resolves property name to physical DB column when metadata mapping exists.
     */
    private function resolveEntityColumn(string $entityClass, string $propertyOrColumn): string
    {
        $meta     = $this->entityManager->metadataProvider()->for($entityClass);
        $property = $meta->columns[$propertyOrColumn] ?? null;

        return $property?->column ?? $propertyOrColumn;
    }

    private function resolveRootPropertyColumnRef(string $propertyOrColumn): string
    {
        $propertyOrColumn = trim($propertyOrColumn);
        if ($propertyOrColumn === '' || $propertyOrColumn === '*') {
            return $propertyOrColumn;
        }

        if (str_contains($propertyOrColumn, '.')) {
            return $propertyOrColumn;
        }

        return $this->rootRef() . '.' . $this->resolveEntityColumn($this->entityClass, $propertyOrColumn);
    }

    private function ensureRootSelectionForComputedColumn(): void
    {
        if ($this->explicitSelect) {
            return;
        }

        $this->query->select($this->rootRef() . '.*');
        $this->explicitSelect = true;
    }

    private function resolveSearchProfile(?string $profile): FullTextSearchMetadata
    {
        $meta     = $this->entityManager->metadataProvider()->for($this->entityClass);
        $searches = $meta->fullTextSearches;
        if ($searches === []) {
            throw new InvalidArgumentException('Entity ' . $this->entityClass . ' has no FullTextSearch profiles.');
        }

        if ($profile !== null) {
            $profile = trim($profile);
            if ($profile === '') {
                throw new InvalidArgumentException('FullTextSearch profile name must be non-empty.');
            }

            return $searches[$profile]
                ?? throw new InvalidArgumentException('FullTextSearch profile not found: ' . $profile);
        }

        $default = null;
        foreach ($searches as $search) {
            if (!$search->default) {
                continue;
            }

            $default = $search;
            break;
        }

        if ($default !== null) {
            return $default;
        }

        if (count($searches) === 1) {
            return array_values($searches)[0];
        }

        throw new InvalidArgumentException('FullTextSearch profile is ambiguous for ' . $this->entityClass . '. Pass profile name explicitly.');
    }

    private function searchOptions(FullTextSearchMetadata $search): PgFullTextOptions
    {
        return new PgFullTextOptions(
            config: $search->config,
            queryMode: $search->queryMode,
            rankFunction: $search->rankFunction,
            normalization: $search->normalization,
        );
    }

    private function ensureRootSelection(): void
    {
        if ($this->explicitSelect) {
            return;
        }

        $this->query->select($this->rootRef() . '.*');
        $this->explicitSelect = true;
    }

}
