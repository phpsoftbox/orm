<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata;

use PhpSoftBox\Orm\EntityManagerConfig;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Metadata\MetadataColumnPropertyMapper;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostBelongsTo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetadataColumnPropertyMapper::class)]
final class MetadataColumnPropertyMapperTest extends TestCase
{
    /**
     * Проверяет, что маппер умеет находить имя свойства сущности по имени колонки.
     */
    #[Test]
    public function columnToPropertyReturnsPropertyName(): void
    {
        $config = new EntityManagerConfig(enableBuiltInListeners: false);

        $metadata = new AttributeMetadataProvider(namingConvention: $config->namingConvention);

        $mapper = new MetadataColumnPropertyMapper($metadata);

        self::assertSame('authorId', $mapper->columnToProperty(PostBelongsTo::class, 'author_id'));
        self::assertNull($mapper->columnToProperty(PostBelongsTo::class, 'unknown_column'));
    }

    /**
     * Проверяет, что маппер умеет находить имя колонки по имени свойства сущности.
     */
    #[Test]
    public function propertyToColumnReturnsColumnName(): void
    {
        $config = new EntityManagerConfig(enableBuiltInListeners: false);

        $metadata = new AttributeMetadataProvider(namingConvention: $config->namingConvention);

        $mapper = new MetadataColumnPropertyMapper($metadata);

        self::assertSame('author_id', $mapper->propertyToColumn(PostBelongsTo::class, 'authorId'));
        self::assertNull($mapper->propertyToColumn(PostBelongsTo::class, 'unknownProperty'));
    }
}
