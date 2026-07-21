<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior\Fixtures;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\Id;

#[Entity(table: 'uninitialized_required_entities')]
final class UninitializedRequiredEntity implements EntityInterface
{
    #[Id]
    #[GeneratedValue(strategy: 'auto')]
    #[Column(type: 'int')]
    public ?int $id = null;

    #[Column(type: 'string')]
    public string $name;

    public function id(): ?int
    {
        return $this->id;
    }
}
