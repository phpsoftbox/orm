<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Conventions;

use PhpSoftBox\Inflector\Contracts\InflectorInterface;

/**
 * Conventions на базе Inflector.
 */
final readonly class InflectorNamingConvention implements NamingConventionInterface
{
    public function __construct(
        private InflectorInterface
    $inflector)
    {
    }

    public function entityTable(string $entityClassShortName): string
    {
        return $this->inflector->pluralize($this->inflector->tableize($entityClassShortName));
    }

    public function belongsToJoinProperty(string $relationProperty): string
    {
        return $relationProperty . 'Id';
    }

    public function hasOneManyForeignKeyColumn(string $relationProperty): string
    {
        return $this->inflector->tableize($relationProperty) . '_id';
    }

    public function belongsToManyPivotTable(string $leftTable, string $rightTable): string
    {
        $owner = $this->inflector->singularize($leftTable);

        // Нормализуем related к plural: если уже plural, останется plural; если вдруг singular — станет plural.
        $related = $this->inflector->pluralize($this->inflector->singularize($rightTable));

        return $owner . '_' . $related;
    }

    public function belongsToManyPivotKey(string $table): string
    {
        return $this->inflector->singularize($table) . '_id';
    }

    public function hasManyThroughFirstKey(string $sourceTable): string
    {
        return $this->inflector->singularize($sourceTable) . '_id';
    }

    public function hasManyThroughSecondKey(string $targetTable): string
    {
        return $this->inflector->singularize($targetTable) . '_id';
    }
}
