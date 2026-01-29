<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasManyThrough;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'countries', connection: 'main')]
final class CountryHasManyThroughDefaultsEntityManager implements EntityInterface
{
    #[Id]
    #[Column(type: 'primary')]
    public int $id;

    #[HasManyThrough(
        targetEntity: PostHasManyThroughDefaultsEntityManager::class,
        throughEntity: CityHasManyThroughDefaultsEntityManager::class,
    )]
    public array $posts = [];

    public function id(): int
    {
        return $this->id;
    }
}
