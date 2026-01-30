<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Fixtures\App\Entity;

use PhpSoftBox\Orm\Metadata\Attributes\Entity;

#[Entity(table: 'users')]
final class UserEntity
{
}
