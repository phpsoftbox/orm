<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior\Command;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;

interface EntityCommandInterface
{
    public function orm(): EntityManagerInterface;

    public function entity(): EntityInterface;

    public function state(): MutableEntityState;
}
