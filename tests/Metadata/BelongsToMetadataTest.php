<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata;

use InvalidArgumentException;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\EntityWithBelongsTo;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\EntityWithBelongsToAndManyToOneConflict;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\EntityWithBelongsToDefaults;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMetadataProvider::class)]
final class BelongsToMetadataTest extends TestCase
{
    /**
     * Проверяет, что #[BelongsTo] читается как связь типа many_to_one.
     */
    #[Test]
    public function belongsToCreatesManyToOneRelation(): void
    {
        $provider = new AttributeMetadataProvider();
        $meta = $provider->for(EntityWithBelongsTo::class);

        self::assertArrayHasKey('author', $meta->relations);
        self::assertSame('many_to_one', $meta->relations['author']->type);
        self::assertSame('authorId', $meta->relations['author']->joinColumn);
        self::assertSame('id', $meta->relations['author']->referencedColumn);
    }

    /**
     * Проверяет дефолт referencedColumn у #[BelongsTo] (если не задан, должен быть 'id').
     */
    #[Test]
    public function belongsToDefaultsReferencedColumnToId(): void
    {
        $provider = new AttributeMetadataProvider();
        $meta = $provider->for(EntityWithBelongsToDefaults::class);

        self::assertArrayHasKey('author', $meta->relations);
        self::assertSame('id', $meta->relations['author']->referencedColumn);
    }

    /**
     * Проверяет, что нельзя ставить #[BelongsTo] и #[ManyToOne] на одно и то же свойство.
     */
    #[Test]
    public function throwsOnBelongsToAndManyToOneConflict(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $provider = new AttributeMetadataProvider();
        $provider->for(EntityWithBelongsToAndManyToOneConflict::class);
    }
}
