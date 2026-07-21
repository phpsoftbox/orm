<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Exception;

final class EntityPersistException extends OrmException
{
    public static function missingGeneratedId(string $entityClass, string $table): self
    {
        return new self('Generated id was not assigned for entity ' . $entityClass . ' (table: ' . $table . ').');
    }

    /**
     * @param class-string $entityClass
     * @param 'insert'|'update' $operation
     */
    public static function missingRequiredValue(
        string $entityClass,
        string $property,
        string $column,
        string $operation,
    ): self {
        return new self(
            'Cannot ' . $operation . ' entity ' . $entityClass
            . ': required mapped property $' . $property
            . ' (column "' . $column . '") is null or missing after lifecycle listeners.',
        );
    }
}
