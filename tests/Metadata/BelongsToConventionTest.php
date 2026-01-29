<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata;

use PhpSoftBox\Orm\EntityManagerConfig;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\EntityWithBelongsToConvention;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMetadataProvider::class)]
final class BelongsToConventionTest extends TestCase
{
    /**
     * Проверяет конвенцию BelongsTo: если joinColumn не указан,
     * то он выводится из имени свойства связи как <relationProperty>Id.
     */
    #[Test]
    public function infersJoinColumnFromRelationPropertyName(): void
    {
        $config = new EntityManagerConfig(enableBuiltInListeners: false);
        $provider = new AttributeMetadataProvider(namingConvention: $config->namingConvention);
        $meta = $provider->for(EntityWithBelongsToConvention::class);

        self::assertArrayHasKey('author', $meta->relations);
        self::assertSame('authorId', $meta->relations['author']->joinColumn);

        // и при этом authorId может быть замапплен в snake_case колонку
        self::assertSame('author_id', $meta->columns['authorId']->column);
    }
}
