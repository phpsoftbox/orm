<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasManyThrough;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'countries')]
final class Country implements EntityInterface
{
    #[Id]
    public int $id;

    /**
     * Посты, доступные через пользователей.
     *
     * firstKey/secondKey будут вычислены автоматически:
     * - firstKey: country_id
     * - secondKey: post_id
     */
    #[HasManyThrough(targetEntity: Post::class, throughEntity: User::class)]
    public array $posts = [];

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}
