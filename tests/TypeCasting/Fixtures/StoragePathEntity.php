<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\TypeCasting\Fixtures;

use PhpSoftBox\DataCasting\Options\StoragePathCastOptions;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;

#[Entity(table: 'storage_paths')]
final class StoragePathEntity
{
    #[Column(type: 'storage_path', nullable: true)]
    public mixed $avatarPath = null;

    #[Column(type: 'storage_path', nullable: true, options: new StoragePathCastOptions('public'))]
    public mixed $publicAvatarPath = null;
}
