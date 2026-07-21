<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Tests\Fixtures\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(EntityCollection::class)]
final class EntityCollectionTest extends TestCase
{
    /**
     * Проверяет, что коллекция создаётся из массива сущностей и корректно считает элементы.
     */
    #[Test]
    public function buildsFromArrayAndCounts(): void
    {
        $items = [
            new User(Uuid::uuid7(), 'a'),
            new User(Uuid::uuid7(), 'b'),
        ];

        $collection = EntityCollection::from($items);

        self::assertSame(2, $collection->count());
        self::assertSame($items, $collection->all());
    }
}
