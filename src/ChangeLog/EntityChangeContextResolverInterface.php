<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\ChangeLog;

interface EntityChangeContextResolverInterface
{
    public function resolve(): EntityChangeContext;
}
