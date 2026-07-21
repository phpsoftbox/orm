<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\TypeCasting\Fixtures;

use DateTimeImmutable;
use PhpSoftBox\DataCasting\Options\BoolCastOptions;
use PhpSoftBox\DataCasting\Options\DatetimeCastOptions;
use PhpSoftBox\DataCasting\Options\DecimalCastOptions;
use PhpSoftBox\DataCasting\Options\EnumCastOptions;
use PhpSoftBox\DataCasting\Options\PgArrayCastOptions;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;

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
