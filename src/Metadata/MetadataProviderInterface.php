<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata;

interface MetadataProviderInterface
{
    /**
     * @param class-string $entityClass
     */
    public function for(string $entityClass): ClassMetadata;
}
