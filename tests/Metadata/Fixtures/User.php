<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'users')]
final class User implements EntityInterface
{
    #[Id]
    public int $id;

    public int $country_id;

    public int $post_id;

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}
