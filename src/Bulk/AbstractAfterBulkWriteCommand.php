<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Bulk;

use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;

abstract readonly class AbstractAfterBulkWriteCommand extends AbstractBulkWriteCommand
{
    /**
     * @param class-string $entityClass
     */
    public function __construct(
        EntityManagerInterface $orm,
        string $entityClass,
        LookupSpec $lookup,
        BulkWriteAction $action,
        MutableBulkWriteState $state,
        private BulkWriteResult $result,
    ) {
        parent::__construct($orm, $entityClass, $lookup, $action, $state);
    }

    public function result(): BulkWriteResult
    {
        return $this->result;
    }
}
