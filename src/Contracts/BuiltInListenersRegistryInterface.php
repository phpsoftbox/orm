<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

interface BuiltInListenersRegistryInterface
{
    /**
     * @return list<object>
     */
    public function listeners(): array;
}
