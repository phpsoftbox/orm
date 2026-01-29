<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Persistence\Fixtures;

use DateTimeImmutable;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Tests\TypeCasting\Fixtures\StatusEnum;
use PhpSoftBox\Orm\TypeCasting\Options\DatetimeCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\EnumCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\PgArrayCastOptions;

#[Entity(table: 'persist_all_types')]
final class AllTypesPersistEntity implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,

        #[Column(type: 'datetime', options: new DatetimeCastOptions(formatTo: 'Y-m-d H:i:s', formatFrom: 'Y-m-d H:i:s'))]
        public DateTimeImmutable $created,

        #[Column(type: 'enum', options: new EnumCastOptions(enumClass: StatusEnum::class))]
        public StatusEnum $status,

        #[Column(type: 'pg_array', options: new PgArrayCastOptions(itemType: 'int'))]
        public array $ids,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}

