<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata;

use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\DummyEntityChangelogLogger;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\EntityWithChangelog;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\UserEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMetadataProvider::class)]
final class AttributeMetadataProviderTest extends TestCase
{
    #[Test]
    public function readsEntityAndColumnsMetadata(): void
    {
        $provider = new AttributeMetadataProvider();

        $meta = $provider->for(UserEntity::class);

        self::assertSame(UserEntity::class, $meta->class);
        self::assertSame('users', $meta->table);
        self::assertSame('main', $meta->connection);

        self::assertSame(['id'], $meta->pkProperties);
        self::assertSame('uuid', $meta->idGenerationStrategy);

        self::assertArrayHasKey('id', $meta->columns);
        self::assertArrayHasKey('name', $meta->columns);
        self::assertArrayNotHasKey('computed', $meta->columns);

        self::assertSame('id', $meta->columns['id']->column);
        self::assertSame('uuid', $meta->columns['id']->type);

        self::assertSame('name', $meta->columns['name']->column);
        self::assertSame('string', $meta->columns['name']->type);
        self::assertSame(255, $meta->columns['name']->length);
    }

    #[Test]
    public function readsChangelogMetadata(): void
    {
        $provider = new AttributeMetadataProvider();

        $meta = $provider->for(EntityWithChangelog::class);

        self::assertNotNull($meta->changelog);
        self::assertTrue($meta->changelog->enabled);
        self::assertSame(DummyEntityChangelogLogger::class, $meta->changelog->logHandler);
        self::assertSame(['password', 'token'], $meta->changelog->ignore);
    }
}
