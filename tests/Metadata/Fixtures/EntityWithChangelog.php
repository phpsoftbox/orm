<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Metadata\Attributes\Changelog;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'entity_with_changelog')]
#[Changelog(logHandler: DummyEntityChangelogLogger::class, ignore: ['password', ' token ', 'password', ''])]
final class EntityWithChangelog
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
    ) {
    }
}
