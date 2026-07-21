<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Scope;

use PhpSoftBox\Orm\Relation\Scope\RelationScopeInterface;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeQuery;

final class VisibleCommentsScope implements RelationScopeInterface
{
    public function apply(RelationScopeQuery $query): void
    {
        $query
            ->whereNotIn('body', ['b'])
            ->orderBy('id', 'DESC');
    }
}
