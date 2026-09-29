<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Bulk\Fixtures;

use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use PhpSoftBox\Orm\Bulk\OnBulkUpdate;

/**
 * Listener сущности для bulk-событий: запоминает классы сущностей из полученных событий.
 */
final class BulkRecordingListener
{
    /**
     * @var list<class-string>
     */
    public static array $entities = [];

    #[Listen(OnBulkUpdate::class)]
    public function onBulkUpdate(OnBulkUpdate $event): void
    {
        self::$entities[] = $event->entityClass();
    }
}
