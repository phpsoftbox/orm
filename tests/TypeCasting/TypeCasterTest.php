<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\TypeCasting;

use DateTimeImmutable;
use DateTimeInterface;
use PhpSoftBox\Orm\Metadata\PropertyMetadata;
use PhpSoftBox\Orm\TypeCasting\DefaultTypeCasterFactory;
use PhpSoftBox\Orm\TypeCasting\Handlers\DateTimeHandler;
use PhpSoftBox\Orm\TypeCasting\Handlers\UuidHandler;
use PhpSoftBox\Orm\TypeCasting\TypeCaster;
use PhpSoftBox\Orm\TypeCasting\OrmTypeCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[CoversClass(TypeCaster::class)]
#[CoversClass(OrmTypeCaster::class)]
final class TypeCasterTest extends TestCase
{
    /**
     * Проверяет, что uuid handler поддерживает UuidInterface и строки.
     */
    #[Test]
    public function uuidIsCastedBothWays(): void
    {
        $caster = new DefaultTypeCasterFactory()->create();

        $uuid = Uuid::uuid7();

        $db = $caster->castTo('uuid', $uuid);
        self::assertSame($uuid->toString(), $db);

        $php = $caster->castFrom('uuid', $uuid->toString());
        self::assertInstanceOf(UuidInterface::class, $php);
        self::assertSame($uuid->toString(), $php->toString());
    }

    /**
     * Проверяет, что json handler сериализует массив и десериализует строку.
     */
    #[Test]
    public function jsonIsCastedBothWays(): void
    {
        $caster = new DefaultTypeCasterFactory()->create();

        $db = $caster->castTo('json', ['a' => 1]);
        self::assertSame('{"a":1}', $db);

        $php = $caster->castFrom('json', '{"a":1}');
        self::assertSame(['a' => 1], $php);
    }

    /**
     * Проверяет, что datetime handler принимает DateTimeInterface и возвращает настроенный класс.
     */
    #[Test]
    public function datetimeSupportsDateTimeInterface(): void
    {
        $caster = new OrmTypeCaster([
            new DateTimeHandler(dateTimeClass: DateTimeImmutable::class),
        ]);

        $dt = new DateTimeImmutable('2026-01-15T12:00:00+00:00');

        $db = $caster->castTo('datetime', $dt, ['type' => 'datetime']);
        self::assertSame('2026-01-15T12:00:00+00:00', $db);

        $php = $caster->castFrom('datetime', '2026-01-15T12:00:00+00:00');
        self::assertInstanceOf(DateTimeImmutable::class, $php);
        self::assertSame('2026-01-15T12:00:00+00:00', $php->format(DateTimeImmutable::ATOM));
    }

    /**
     * Проверяет castArray(): кастинг массива по конфигурации.
     */
    #[Test]
    public function castArrayCastsConfiguredKeys(): void
    {
        $caster = (new DefaultTypeCasterFactory())->create();

        $casted = $caster->castArray([
            'key1' => 'datetime',
            'key2' => 'uuid',
            'key3' => 'json',
        ], [
            'key1' => '2022-01-01T00:00:00+00:00',
            'key2' => '123e4567-e89b-12d3-a456-426655440000',
            'key3' => ['a' => 1],
            'untouched' => 123,
        ]);

        self::assertInstanceOf(DateTimeInterface::class, $casted['key1']);
        self::assertSame('2022-01-01T00:00:00+00:00', $casted['key1']->format(DateTimeInterface::ATOM));

        self::assertInstanceOf(UuidInterface::class, $casted['key2']);
        self::assertSame('123e4567-e89b-12d3-a456-426655440000', $casted['key2']->toString());

        self::assertSame(['a' => 1], $casted['key3']);
        self::assertSame(123, $casted['untouched']);
    }

    /**
     * Проверяет, что если в конфиге castArray передан handler как class-string,
     * то он создаётся и применяется.
     */
    #[Test]
    public function castArraySupportsHandlerClassString(): void
    {
        $caster = new OrmTypeCaster([
            new UuidHandler(),
        ]);

        $casted = $caster->castArray([
            'id' => UuidHandler::class,
        ], [
            'id' => '123e4567-e89b-12d3-a456-426655440000',
        ]);

        self::assertInstanceOf(UuidInterface::class, $casted['id']);
        self::assertSame('123e4567-e89b-12d3-a456-426655440000', $casted['id']->toString());
    }
}
