<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Repository;

use DateTimeImmutable;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Tests\Repository\Fixtures\MappedEntity;
use PhpSoftBox\Orm\TypeCasting\DefaultTypeCasterFactory;
use PhpSoftBox\Orm\TypeCasting\Options\TypeCastOptionsManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[CoversClass(AutoEntityMapper::class)]
final class AutoEntityMapperTest extends TestCase
{
    /**
     * Проверяет auto-hydrate: строка из БД преобразуется в сущность на основе метаданных и TypeCaster.
     */
    #[Test]
    public function hydrateCastsValuesUsingTypeCaster(): void
    {
        $metadata = new AttributeMetadataProvider();
        $caster   = new DefaultTypeCasterFactory()->create();

        $mapper = new AutoEntityMapper($metadata, $caster, new TypeCastOptionsManager());

        $row = [
            'id'      => '123e4567-e89b-12d3-a456-426655440000',
            'created' => '2022-01-01T00:00:00+00:00',
        ];

        $entity = $mapper->hydrate(MappedEntity::class, $row);

        self::assertInstanceOf(MappedEntity::class, $entity);
        self::assertInstanceOf(UuidInterface::class, $entity->id);
        self::assertSame('123e4567-e89b-12d3-a456-426655440000', $entity->id->toString());
        self::assertInstanceOf(DateTimeImmutable::class, $entity->created);
        self::assertSame('2022-01-01T00:00:00+00:00', $entity->created->format(DateTimeImmutable::ATOM));
    }

    /**
     * Проверяет auto-extract: сущность преобразуется в массив для БД с кастингом.
     */
    #[Test]
    public function extractCastsValuesUsingTypeCaster(): void
    {
        $metadata = new AttributeMetadataProvider();
        $caster   = new DefaultTypeCasterFactory()->create();

        $mapper = new AutoEntityMapper($metadata, $caster, new TypeCastOptionsManager());

        $entity = new MappedEntity();

        $entity->id      = Uuid::fromString('123e4567-e89b-12d3-a456-426655440000');
        $entity->created = new DateTimeImmutable('2022-01-01T00:00:00+00:00');

        $data = $mapper->extract($entity);

        self::assertSame('123e4567-e89b-12d3-a456-426655440000', $data['id']);
        self::assertSame('2022-01-01T00:00:00+00:00', $data['created']);
    }
}
