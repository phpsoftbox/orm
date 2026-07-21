<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Attributes;

use Attribute;
use PhpSoftBox\Database\Postgres\FullText\PgFullTextQueryMode;
use PhpSoftBox\Database\Postgres\FullText\PgFullTextRankFunction;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class FullTextSearch
{
    public function __construct(
        public string $name,
        public string $vectorColumn,
        public string $config = 'simple',
        public PgFullTextQueryMode $queryMode = PgFullTextQueryMode::Websearch,
        public PgFullTextRankFunction $rankFunction = PgFullTextRankFunction::RankCd,
        public ?int $normalization = null,
        public bool $default = false,
    ) {
    }
}
