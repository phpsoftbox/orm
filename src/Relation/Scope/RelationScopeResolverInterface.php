<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Relation\Scope;

interface RelationScopeResolverInterface
{
    /**
     * @param class-string<RelationScopeInterface> $scope
     */
    public function resolve(string $scope): RelationScopeInterface;
}
