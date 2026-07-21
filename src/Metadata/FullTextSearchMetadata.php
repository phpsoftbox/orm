<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata;

use PhpSoftBox\Database\Postgres\FullText\PgFullTextQueryMode;
use PhpSoftBox\Database\Postgres\FullText\PgFullTextRankFunction;

final readonly class FullTextSearchMetadata
{
    public function __construct(
        public string $name,
        public string $vectorColumn,
        public string $config,
        public PgFullTextQueryMode $queryMode,
        public PgFullTextRankFunction $rankFunction,
        public ?int $normalization = null,
        public bool $default = false,
    ) {
    }
}
