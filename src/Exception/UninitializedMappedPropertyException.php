<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Exception;

final class UninitializedMappedPropertyException extends OrmException
{
    /**
     * @param class-string $entityClass
     */
    public static function forProperty(string $entityClass, string $property): self
    {
        return new self(
            'Mapped property ' . $entityClass . '::$' . $property
            . ' is not initialized. Initialize required entity properties in the constructor; '
            . 'listener-managed properties must use an explicit nullable transient value.',
        );
    }
}
