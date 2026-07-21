<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

/**
 * Сущность без #[SoftDelete]: remove() выполняет физический DELETE.
 */
#[Entity(table: 'hard_delete_status_entities')]
final class HardDeleteStatusEntity implements EntityInterface
{
    public function __construct(
        #[Id]
        #[Column(type: 'int')]
        public int $id,
        #[Column(type: 'string')]
        public string $status = 'active',
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}
