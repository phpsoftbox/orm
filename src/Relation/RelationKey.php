<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Relation;

/**
 * Ключ связи, разрешённый через метаданные: физическая колонка и соответствующее ей свойство сущности.
 */
final readonly class RelationKey
{
    public function __construct(
        public string $column,
        public string $property,
    ) {
    }
}
