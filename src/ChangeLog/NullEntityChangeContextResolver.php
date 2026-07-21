<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\ChangeLog;

final class NullEntityChangeContextResolver implements EntityChangeContextResolverInterface
{
    public function resolve(): EntityChangeContext
    {
        return new EntityChangeContext();
    }
}
