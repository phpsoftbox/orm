<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Relation\Scope;

final readonly class DefaultRelationScopeResolver implements RelationScopeResolverInterface
{
    public function resolve(string $scope): RelationScopeInterface
    {
        return new $scope();
    }
}
