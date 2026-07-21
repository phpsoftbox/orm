<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata;

use PhpSoftBox\Orm\EntityManagerConfig;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\Country;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\EntityWithoutTable;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\RoleBelongsToManyUsersWithPivotOwner;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\UserWithBelongsToManyDefaults;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMetadataProvider::class)]
final class EntityTableConventionTest extends TestCase
{
    /**
     * Проверяет, что если #[Entity(table: null)] не задан, имя таблицы вычисляется по имени класса:
     * EntityWithoutTable -> entity_without_tables.
     */
    #[Test]
    public function entityWithoutTableUsesClassNameConvention(): void
    {
        $config = new EntityManagerConfig(enableBuiltInListeners: false);

        $provider = new AttributeMetadataProvider(namingConvention: $config->namingConvention);

        $meta = $provider->for(EntityWithoutTable::class);

        self::assertSame('entity_without_tables', $meta->table);
    }

    /**
     * Проверяет дефолты BelongsToMany, когда pivotTable/keys не заданы в атрибуте.
     */
    #[Test]
    public function belongsToManyDefaultsAreGuessed(): void
    {
        $config = new EntityManagerConfig(enableBuiltInListeners: false);

        $provider = new AttributeMetadataProvider(namingConvention: $config->namingConvention);

        $meta = $provider->for(UserWithBelongsToManyDefaults::class);

        $rel = $meta->relations['roles'] ?? null;
        self::assertNotNull($rel);

        self::assertSame('belongs_to_many', $rel->type);
        self::assertSame('user_roles', $rel->pivotTable);
        self::assertSame('user_id', $rel->foreignPivotKey);
        self::assertSame('role_id', $rel->relatedPivotKey);
    }

    /**
     * Проверяет дефолты HasManyThrough, когда firstKey/secondKey не заданы в атрибуте.
     */
    #[Test]
    public function hasManyThroughDefaultsAreGuessed(): void
    {
        $config = new EntityManagerConfig(enableBuiltInListeners: false);

        $provider = new AttributeMetadataProvider(namingConvention: $config->namingConvention);

        $meta = $provider->for(Country::class);

        $rel = $meta->relations['posts'] ?? null;
        self::assertNotNull($rel);

        self::assertSame('has_many_through', $rel->type);
        self::assertSame('country_id', $rel->firstKey);
        self::assertSame('post_id', $rel->secondKey);
        self::assertSame('id', $rel->localKey);
        self::assertSame('id', $rel->targetKey);
    }

    /**
     * Проверяет pivotOwner: если связь объявлена на обратной стороне, но pivotOwner задан,
     * pivotTable всё равно генерируется как owner-first (user_roles).
     */
    #[Test]
    public function belongsToManyPivotOwnerForcesOwnerFirstPivotTable(): void
    {
        $config = new EntityManagerConfig(enableBuiltInListeners: false);

        $provider = new AttributeMetadataProvider(namingConvention: $config->namingConvention);

        $meta = $provider->for(RoleBelongsToManyUsersWithPivotOwner::class);

        $rel = $meta->relations['users'] ?? null;
        self::assertNotNull($rel);

        self::assertSame('belongs_to_many', $rel->type);
        self::assertSame('user_roles', $rel->pivotTable);
        self::assertSame('role_id', $rel->foreignPivotKey);
        self::assertSame('user_id', $rel->relatedPivotKey);
    }
}
