<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'listener_timestamp_entities')]
final class ListenerTimestampEntity implements EntityInterface
{
    #[Id]
    #[GeneratedValue(strategy: 'auto')]
    #[Column(type: 'int')]
    public ?int $id = null;

    #[Column(type: 'string')]
    public string $name;

    #[Column(name: 'created_datetime', type: 'date_point')]
    public ?DatePoint $createdDatetime = null;

    #[Column(name: 'updated_datetime', type: 'date_point')]
    public ?DatePoint $updatedDatetime = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
