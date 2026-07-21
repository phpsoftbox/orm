<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures;

use PhpSoftBox\Database\Postgres\FullText\PgFullTextQueryMode;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\FullTextSearch;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'products')]
#[FullTextSearch(
    name: 'natural',
    vectorColumn: 'natural_search_vector',
    config: 'russian',
    default: true,
)]
#[FullTextSearch(
    name: 'technical',
    vectorColumn: 'technical_search_vector',
    config: 'simple',
    queryMode: PgFullTextQueryMode::Plain,
)]
final class SearchableProduct implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $name,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}
