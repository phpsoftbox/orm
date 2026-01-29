<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata;

/**
 * Маппинг property <-> column.
 */
interface ColumnPropertyMapperInterface
{
    public function columnToProperty(string $entityClass, string $column): ?string;

    public function propertyToColumn(string $entityClass, string $property): ?string;
}
