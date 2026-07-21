<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasManyThrough;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'through_companies')]
final class ThroughCompany implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $name,
    ) {
    }

    /**
     * @var EntityCollection<ThroughWorkItem>|null
     */
    #[HasManyThrough(
        targetEntity: ThroughWorkItem::class,
        throughEntity: ThroughLink::class,
        firstKey: 'company_id',
        secondKey: 'work_item_id',
        localKey: 'id',
        targetKey: 'id',
    )]
    public ?EntityCollection $workItems = null;

    public function id(): int
    {
        return $this->id;
    }
}
