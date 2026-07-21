<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\FullTextSearch;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'products')]
#[FullTextSearch(name: 'natural', vectorColumn: 'natural_search_vector', config: 'russian')]
#[FullTextSearch(name: 'technical', vectorColumn: 'technical_search_vector', config: 'simple')]
final class AmbiguousSearchableProduct implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}
