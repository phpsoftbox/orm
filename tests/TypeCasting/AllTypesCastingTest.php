<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\TypeCasting;

use DateTimeImmutable;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Tests\TypeCasting\Fixtures\AllTypesEntity;
use PhpSoftBox\Orm\Tests\TypeCasting\Fixtures\StatusEnum;
use PhpSoftBox\Orm\TypeCasting\DefaultTypeCasterFactory;
use PhpSoftBox\Orm\TypeCasting\Options\TypeCastOptionsManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AutoEntityMapper::class)]
final class AllTypesCastingTest extends TestCase
{
    /**
     * Проверяет кастинг всех поддержанных типов: bool/decimal/datetime/enum/pg_array.
     */
    #[Test]
    public function allTypesAreCasted(): void
    {
        $metadata = new AttributeMetadataProvider();
        $caster   = new DefaultTypeCasterFactory()->create();

        $mapper = new AutoEntityMapper($metadata, $caster, new TypeCastOptionsManager());

        $row = [
            'isActive' => 't',
            'balance'  => '10.5000',
            'created'  => '2026-01-15 12:00:00',
            'status'   => 'active',
            'ids'      => '{1,2,3}',
        ];

        $entity = $mapper->hydrate(AllTypesEntity::class, $row);

        self::assertTrue($entity->isActive);
        self::assertSame('10.5', $entity->balance);
        self::assertInstanceOf(DateTimeImmutable::class, $entity->created);
        self::assertSame('2026-01-15 12:00:00', $entity->created->format('Y-m-d H:i:s'));
        self::assertSame(StatusEnum::Active, $entity->status);
        self::assertSame([1, 2, 3], $entity->ids);

        $back = $mapper->extract($entity);
        self::assertSame(true, $back['isActive']);
        self::assertSame('10.5', $back['balance']);
        self::assertSame('2026-01-15 12:00:00', $back['created']);
        self::assertSame('active', $back['status']);
        self::assertSame('{1,2,3}', $back['ids']);
    }
}
