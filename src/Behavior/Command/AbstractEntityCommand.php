<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior\Command;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;

abstract readonly class AbstractEntityCommand implements EntityCommandInterface
{
    public function __construct(
        private EntityManagerInterface $orm,
        private EntityInterface $entity,
        private MutableEntityState $state,
    ) {
    }

    public function orm(): EntityManagerInterface
    {
        return $this->orm;
    }

    public function entity(): EntityInterface
    {
        return $this->entity;
    }

    public function state(): MutableEntityState
    {
        return $this->state;
    }
}
