<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata;

use Throwable;

/**
 * ColumnPropertyMapper на базе MetadataProvider.
 */
final class MetadataColumnPropertyMapper implements ColumnPropertyMapperInterface
{
    /**
     * @var array<class-string, array<string, string>>
     */
    private array $columnToPropertyCache = [];

    public function __construct(private readonly MetadataProviderInterface $metadata)
    {
    }

    public function columnToProperty(string $entityClass, string $column): ?string
    {
        try {
            $map = $this->columnToPropertyCache[$entityClass] ??= $this->buildColumnToPropertyMap($entityClass);
            return $map[$column] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    public function propertyToColumn(string $entityClass, string $property): ?string
    {
        try {
            $meta = $this->metadata->for($entityClass);
            return $meta->columns[$property]->column ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildColumnToPropertyMap(string $entityClass): array
    {
        $meta = $this->metadata->for($entityClass);

        $map = [];
        foreach ($meta->columns as $property => $colMeta) {
            $map[$colMeta->column] = $property;
        }

        return $map;
    }
}
