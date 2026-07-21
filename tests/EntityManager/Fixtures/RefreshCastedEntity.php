<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\DataCasting\Options\EnumCastOptions;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'refresh_casted_entities')]
final class RefreshCastedEntity implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(name: 'is_enabled', type: 'bool')]
        public bool $isEnabled,
        #[Column(name: 'created_datetime', type: 'date_point', nullable: true)]
        public ?DatePoint $createdDatetime,
        #[Column(type: 'enum', options: new EnumCastOptions(enumClass: RefreshStatus::class))]
        public RefreshStatus $status,
        #[Column(type: 'json')]
        public array $payload,
        #[Column(name: 'external_id', type: 'uuid', nullable: true)]
        public ?UuidInterface $externalId,
        #[Column(type: 'string', nullable: true)]
        public ?string $note,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}
