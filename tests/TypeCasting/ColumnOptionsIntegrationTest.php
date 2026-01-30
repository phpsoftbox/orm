<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\TypeCasting;

use DateTimeImmutable;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Tests\TypeCasting\Fixtures\OptionsEntity;
use PhpSoftBox\Orm\TypeCasting\DefaultTypeCasterFactory;
use PhpSoftBox\Orm\TypeCasting\Options\TypeCastOptionsManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AutoEntityMapper::class)]
final class ColumnOptionsIntegrationTest extends TestCase
{
    /**
     * Проверяет, что options из #[Column(options: ...)] попадают в PropertyMetadata и влияют на кастинг.
     */
    #[Test]
    public function columnOptionsAffectCasting(): void
    {
        $metadata = new AttributeMetadataProvider();
        $caster   = new DefaultTypeCasterFactory()->create();

        $mapper = new AutoEntityMapper($metadata, $caster, new TypeCastOptionsManager());

        $row = [
            'created' => '2026-01-15 12:00:00',
            'payload' => '{"a":1}',
        ];

        $entity = $mapper->hydrate(OptionsEntity::class, $row);

        self::assertInstanceOf(DateTimeImmutable::class, $entity->created);
        self::assertSame('2026-01-15 12:00:00', $entity->created->format('Y-m-d H:i:s'));
        self::assertSame(['a' => 1], $entity->payload);

        $back = $mapper->extract($entity);

        self::assertSame('2026-01-15 12:00:00', $back['created']);
        self::assertSame('{"a":1}', $back['payload']);
    }
}
