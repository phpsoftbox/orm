<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

interface ConnectionEntityManagerFactoryInterface
{
    public function runtimeRegistry(): EntityRuntimeRegistryInterface;

    public function create(string $connectionName = 'default', bool $write = true): EntityManagerInterface;
}
