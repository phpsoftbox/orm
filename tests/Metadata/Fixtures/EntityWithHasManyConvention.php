<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\HasMany;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'posts_hm')]
final class EntityWithHasManyConvention implements EntityInterface
{
    /**
     * @param EntityCollection<EntityInterface> $comments
     */
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,

        // Свойство связи названо 'post', чтобы конвенция дала post_id.
        // В реальной модели это поле будет называться user/post и т.д.
        #[HasMany(targetEntity: UserEntity::class)]
        public EntityCollection $post,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
