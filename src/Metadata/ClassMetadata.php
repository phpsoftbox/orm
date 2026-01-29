<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata;

use PhpSoftBox\Orm\Metadata\Attributes\Sluggable;

/**
 * Метаданные ORM-сущности.
 *
 * @psalm-type ColumnMap = array<string, PropertyMetadata>
 */
final readonly class ClassMetadata
{
    /**
     * @param class-string $class
     * @param list<string> $pkProperties
     * @param ColumnMap $columns Колонки, индексированные по имени свойства.
     */
    public function __construct(
        public string $class,
        public string $table,
        public ?string $connection,
        /** @var class-string|null */
        public ?string $repository,
        public string $repositoryNamespace,
        public array $pkProperties,
        /** @var 'auto'|'uuid'|'none'|null */
        public ?string $idGenerationStrategy,
        public array $columns,
        public ?SoftDeleteMetadata $softDelete = null,
        /** @var list<Sluggable> */
        public array $sluggables = [],
        /** @var array<string, RelationMetadata> */
        public array $relations = [],
        public array $eventListeners = [],
        public array $hooks = [],
    ) {
    }

    /**
     * @return list<PropertyMetadata>
     */
    public function insertableColumns(): array
    {
        $result = [];
        foreach ($this->columns as $col) {
            if ($col->insertable) {
                $result[] = $col;
            }
        }

        return $result;
    }

    /**
     * @return list<PropertyMetadata>
     */
    public function updatableColumns(): array
    {
        $result = [];
        foreach ($this->columns as $col) {
            if ($col->updatable) {
                $result[] = $col;
            }
        }

        return $result;
    }
}
