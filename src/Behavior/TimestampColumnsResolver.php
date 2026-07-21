<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use DateTimeImmutable;
use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\DataCasting\Options\DatetimeCastOptions;
use PhpSoftBox\Orm\Metadata\ClassMetadata;
use PhpSoftBox\Orm\Metadata\PropertyMetadata;

final readonly class TimestampColumnsResolver
{
    public function createdForInsert(ClassMetadata $meta, DateTimeImmutable $value): ?ResolvedTimestampColumn
    {
        return $this->resolve($meta, 'createdDatetime', 'created_datetime', $value, forUpdate: false);
    }

    public function updatedForInsert(ClassMetadata $meta, DateTimeImmutable $value): ?ResolvedTimestampColumn
    {
        return $this->resolve($meta, 'updatedDatetime', 'updated_datetime', $value, forUpdate: false);
    }

    public function updatedForUpdate(ClassMetadata $meta, DateTimeImmutable $value): ?ResolvedTimestampColumn
    {
        return $this->resolve($meta, 'updatedDatetime', 'updated_datetime', $value, forUpdate: true);
    }

    private function resolve(
        ClassMetadata $meta,
        string $propertyName,
        string $columnName,
        DateTimeImmutable $value,
        bool $forUpdate,
    ): ?ResolvedTimestampColumn {
        $column = $this->resolveColumn($meta, $propertyName, $columnName);
        if ($column === null) {
            return null;
        }

        if ($forUpdate && !$column->updatable) {
            return null;
        }

        if (!$forUpdate && !$column->insertable) {
            return null;
        }

        return new ResolvedTimestampColumn($column, $this->resolveValue($column, $value));
    }

    private function resolveColumn(ClassMetadata $meta, string $propertyName, string $columnName): ?PropertyMetadata
    {
        if (isset($meta->columns[$propertyName])) {
            return $meta->columns[$propertyName];
        }

        foreach ($meta->columns as $column) {
            if ($column->column === $columnName) {
                return $column;
            }
        }

        return null;
    }

    private function resolveValue(PropertyMetadata $column, DateTimeImmutable $value): DateTimeImmutable|DatePoint
    {
        if ($column->type === 'date_point' || $column->type === 'day_point' || $column->type === 'time_point') {
            return DatePoint::from($value);
        }

        $options = $column->options;
        if ($options instanceof DatetimeCastOptions && $options->dateTimeClass === DatePoint::class) {
            return DatePoint::from($value);
        }

        return $value;
    }
}
