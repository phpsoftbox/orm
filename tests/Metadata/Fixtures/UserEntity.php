<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata\Fixtures;

use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\NotMapped;

#[Entity(table: 'users', connection: 'main')]
final class UserEntity
{
    #[Id]
    #[GeneratedValue(strategy: 'uuid')]
    #[Column(type: 'uuid')]
    public string $id;

    #[Column(type: 'string', length: 255)]
    public string $name;

    #[NotMapped]
    public string $computed;
}
