<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Bulk;

use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;

abstract readonly class AbstractBulkWriteCommand
{
    /**
     * @param class-string $entityClass
     */
    public function __construct(
        private EntityManagerInterface $orm,
        private string $entityClass,
        private LookupSpec $lookup,
        private BulkWriteAction $action,
        private MutableBulkWriteState $state,
    ) {
    }

    public function orm(): EntityManagerInterface
    {
        return $this->orm;
    }

    /**
     * @return class-string
     */
    public function entityClass(): string
    {
        return $this->entityClass;
    }

    public function lookup(): LookupSpec
    {
        return $this->lookup;
    }

    public function action(): BulkWriteAction
    {
        return $this->action;
    }

    public function state(): MutableBulkWriteState
    {
        return $this->state;
    }
}
