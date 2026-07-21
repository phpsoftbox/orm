<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeRecord;

final class DummyEntityChangelogLogger implements EntityChangeLoggerInterface
{
    public function log(EntityChangeRecord $record): void
    {
    }
}
