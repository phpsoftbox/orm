<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\TypeCasting\Fixtures;

use DateTimeImmutable;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\TypeCasting\Options\BoolCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\DatetimeCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\DecimalCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\EnumCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\PgArrayCastOptions;

#[Entity(table: 'all_types')]
final class AllTypesEntity
{
    #[Column(type: 'bool', options: new BoolCastOptions())]
    public bool $isActive;

    #[Column(type: 'decimal', options: new DecimalCastOptions(trimTrailingZeros: true))]
    public ?string $balance = null;

    #[Column(type: 'datetime', options: new DatetimeCastOptions(formatTo: 'Y-m-d H:i:s', formatFrom: 'Y-m-d H:i:s', dateTimeClass: DateTimeImmutable::class))]
    public DateTimeImmutable $created;

    #[Column(type: 'enum', options: new EnumCastOptions(enumClass: StatusEnum::class))]
    public StatusEnum $status;

    #[Column(type: 'pg_array', options: new PgArrayCastOptions(itemType: 'int'))]
    public array $ids;
}
