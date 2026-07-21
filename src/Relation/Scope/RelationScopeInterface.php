<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Relation\Scope;

interface RelationScopeInterface
{
    public function apply(RelationScopeQuery $query): void;
}
