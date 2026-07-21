<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata;

use PhpSoftBox\Orm\Behavior\Attributes\EventListener;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\DummyListener;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\EntityWithEventListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMetadataProvider::class)]
#[CoversClass(EventListener::class)]
final class EventListenerMetadataTest extends TestCase
{
    /**
     * Проверяет, что AttributeMetadataProvider читает #[Behavior\EventListener] и сохраняет listener в метаданные.
     */
    #[Test]
    public function readsEventListenerAttribute(): void
    {
        $provider = new AttributeMetadataProvider();

        $meta = $provider->for(EntityWithEventListener::class);

        self::assertSame([DummyListener::class], $meta->eventListeners);
    }
}
