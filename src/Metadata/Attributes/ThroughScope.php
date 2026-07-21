<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeInterface;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class ThroughScope
{
    /**
     * @param class-string<RelationScopeInterface> $scope
     */
    public function __construct(
        public string $scope,
    ) {
    }
}
