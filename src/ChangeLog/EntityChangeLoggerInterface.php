<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\ChangeLog;

interface EntityChangeLoggerInterface
{
    public function log(EntityChangeRecord $record): void;
}
