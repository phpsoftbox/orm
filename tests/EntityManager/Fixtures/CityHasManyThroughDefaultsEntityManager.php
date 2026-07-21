<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'cities', connection: 'main')]
final class CityHasManyThroughDefaultsEntityManager implements EntityInterface
{
    #[Id]
    #[Column(type: 'primary')]
    public int $id;

    #[Column(name: 'country_id', type: 'int')]
    public int $countryId;

    #[Column(name: 'post_id', type: 'int')]
    public int $postId;

    public function id(): int
    {
        return $this->id;
    }
}
