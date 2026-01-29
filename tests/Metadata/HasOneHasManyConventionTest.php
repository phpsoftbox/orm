<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata;

use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\EntityWithHasManyConvention;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\EntityWithHasOneConvention;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMetadataProvider::class)]
final class HasOneHasManyConventionTest extends TestCase
{
    /**
     * Проверяет конвенцию для HasMany: если foreignKey не указан,
     * он выводится из имени свойства связи как <snake_case(property)>_id.
     * Пример: post -> post_id.
     */
    #[Test]
    public function infersHasManyForeignKeyFromRelationProperty(): void
    {
        $config = new \PhpSoftBox\Orm\EntityManagerConfig(enableBuiltInListeners: false);
        $provider = new \PhpSoftBox\Orm\Metadata\AttributeMetadataProvider(namingConvention: $config->namingConvention);
        $meta = $provider->for(EntityWithHasManyConvention::class);

        self::assertArrayHasKey('post', $meta->relations);
        self::assertSame('has_many', $meta->relations['post']->type);
        self::assertSame('post_id', $meta->relations['post']->foreignKey);
    }

    /**
     * Проверяет конвенцию для HasOne: если foreignKey не указан,
     * он выводится из имени свойства связи как <snake_case(property)>_id.
     * Пример: profile -> profile_id.
     */
    #[Test]
    public function infersHasOneForeignKeyFromRelationProperty(): void
    {
        $config = new \PhpSoftBox\Orm\EntityManagerConfig(enableBuiltInListeners: false);
        $provider = new \PhpSoftBox\Orm\Metadata\AttributeMetadataProvider(namingConvention: $config->namingConvention);
        $meta = $provider->for(EntityWithHasOneConvention::class);

        self::assertArrayHasKey('profile', $meta->relations);
        self::assertSame('has_one', $meta->relations['profile']->type);
        self::assertSame('profile_id', $meta->relations['profile']->foreignKey);
    }
}
