<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting;

use DateTimeImmutable;
use DateTimeInterface;
use PhpSoftBox\Orm\TypeCasting\Handlers\BoolOrmHandler;
use PhpSoftBox\Orm\TypeCasting\Handlers\DateTimeHandler;
use PhpSoftBox\Orm\TypeCasting\Handlers\DecimalHandler;
use PhpSoftBox\Orm\TypeCasting\Handlers\EnumHandler;
use PhpSoftBox\Orm\TypeCasting\Handlers\FloatOrmHandler;
use PhpSoftBox\Orm\TypeCasting\Handlers\IntOrmHandler;
use PhpSoftBox\Orm\TypeCasting\Handlers\JsonHandler;
use PhpSoftBox\Orm\TypeCasting\Handlers\PgArrayHandler;
use PhpSoftBox\Orm\TypeCasting\Handlers\StringOrmHandler;
use PhpSoftBox\Orm\TypeCasting\Handlers\UuidHandler;

/**
 * Фабрика дефолтного TypeCaster.
 *
 * Её удобно настраивать через DI:
 *  - выбрать класс для DateTime (DateTimeImmutable/Carbon)
 *  - добавить/заменить handler'ы под свои типы
 */
final readonly class DefaultTypeCasterFactory
{
    /**
     * @param class-string<DateTimeInterface> $dateTimeClass
     */
    public function __construct(
        private string $dateTimeClass = DateTimeImmutable::class,
    ) {
    }

    public function create(): OrmTypeCaster
    {
        return new OrmTypeCaster([
            new IntOrmHandler(),
            new FloatOrmHandler(),
            new StringOrmHandler(),
            new UuidHandler(),
            new JsonHandler(),
            new DateTimeHandler(dateTimeClass: $this->dateTimeClass),
            new BoolOrmHandler(),
            new DecimalHandler(),
            new PgArrayHandler(),
            new EnumHandler(),
        ]);
    }
}
