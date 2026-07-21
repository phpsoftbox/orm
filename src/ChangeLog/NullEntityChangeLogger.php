<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\ChangeLog;

final class NullEntityChangeLogger implements EntityChangeLoggerInterface
{
    public function log(EntityChangeRecord $record): void
    {
    }
}
