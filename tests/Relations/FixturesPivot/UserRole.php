<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\FixturesPivot;

use DateTimeImmutable;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;

#[Entity(table: 'user_role_pivot_rel')]
final class UserRole implements EntityInterface
{
    #[Column(name: 'user_id', type: 'int')]
    public int $userId;

    #[Column(name: 'role_id', type: 'int')]
    public int $roleId;

    #[Column(name: 'created_datetime', type: 'datetime')]
    public DateTimeImmutable $createdDatetime;

    public function id(): int|\Ramsey\Uuid\UuidInterface|null
    {
        // Pivot entity без PK в этом тесте не используется через persist/find.
        return null;
    }
}
