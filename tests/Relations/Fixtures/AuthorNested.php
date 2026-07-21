<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasMany;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'authors_nested')]
final class AuthorNested implements EntityInterface
{
    /**
     * @param EntityCollection<AuthorBookNested>|null $books
     * @param EntityCollection<AuthorAwardNested>|null $awards
     */
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $name,
        #[HasMany(targetEntity: AuthorBookNested::class, foreignKey: 'author_id', localKey: 'id')]
        public ?EntityCollection $books = null,
        #[HasMany(targetEntity: AuthorAwardNested::class, foreignKey: 'author_id', localKey: 'id')]
        public ?EntityCollection $awards = null,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
