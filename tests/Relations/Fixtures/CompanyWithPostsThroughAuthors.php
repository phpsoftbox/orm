<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasManyThrough;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'companies_hmt')]
final class CompanyWithPostsThroughAuthors implements EntityInterface
{
    /**
     * @param EntityCollection<PostForThrough> $posts
     */
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $name,

        /**
         * @var EntityCollection<PostForThrough>
         */
        #[HasManyThrough(
            targetEntity: PostForThrough::class,
            throughEntity: AuthorForThrough::class,
            firstKey: 'company_id',
            secondKey: 'id',
            localKey: 'id',
            targetKey: 'author_id',
        )]
        public EntityCollection $posts,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
