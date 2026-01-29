<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

interface ListenerResolverInterface
{
    /**
     * @param class-string $class
     */
    public function resolve(string $class): object;
}
