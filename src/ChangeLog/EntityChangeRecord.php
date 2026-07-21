<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\ChangeLog;

use DateTimeImmutable;

final readonly class EntityChangeRecord
{
    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param list<array{field: string, before: mixed, after: mixed}> $changes
     */
    public function __construct(
        public EntityChangeAction $action,
        public string $entityClass,
        public int|string|null $entityId,
        public array $before,
        public array $after,
        public array $changes,
        public EntityChangeContext $context,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable(),
    ) {
    }
}
