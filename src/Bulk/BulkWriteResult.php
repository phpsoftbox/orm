<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Bulk;

final readonly class BulkWriteResult
{
    /**
     * @param class-string $entityClass
     * @param list<mixed> $lookupValues
     * @param list<mixed> $missingValues
     */
    public function __construct(
        public string $entityClass,
        public BulkWriteAction $action,
        public int $requestedValues,
        public int $affectedRows,
        public array $lookupValues = [],
        public array $missingValues = [],
    ) {
    }
}
