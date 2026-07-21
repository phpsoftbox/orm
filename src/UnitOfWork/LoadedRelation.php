<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\UnitOfWork;

/**
 * Описание загрузки relation. Fingerprint предназначен для будущих constrained eager loads.
 */
final readonly class LoadedRelation
{
    public function __construct(
        public bool $complete = true,
        public ?string $fingerprint = null,
    ) {
    }
}
