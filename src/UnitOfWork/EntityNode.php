<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\UnitOfWork;

use function array_keys;

/**
 * Runtime-состояние конкретного экземпляра entity.
 *
 * Значения relations остаются в entity; node хранит только факт их загрузки.
 */
final class EntityNode
{
    /** @var array<string, LoadedRelation> */
    private array $loadedRelations = [];

    private ?string $identityKey = null;

    public function __construct(
        private ?EntityState $state = null,
        private ?EntitySnapshot $snapshot = null,
    ) {
    }

    public function state(): ?EntityState
    {
        return $this->state;
    }

    public function setState(EntityState $state): void
    {
        $this->state = $state;
    }

    public function snapshot(): ?EntitySnapshot
    {
        return $this->snapshot;
    }

    public function setSnapshot(EntitySnapshot $snapshot): void
    {
        $this->snapshot = $snapshot;
    }

    public function identityKey(): ?string
    {
        return $this->identityKey;
    }

    public function setIdentityKey(?string $identityKey): void
    {
        $this->identityKey = $identityKey;
    }

    public function isRelationLoaded(string $relation): bool
    {
        return isset($this->loadedRelations[$relation]);
    }

    public function loadedRelation(string $relation): ?LoadedRelation
    {
        return $this->loadedRelations[$relation] ?? null;
    }

    public function markRelationLoaded(string $relation, ?LoadedRelation $state = null): void
    {
        $this->loadedRelations[$relation] = $state ?? new LoadedRelation();
    }

    public function forgetRelation(string $relation): void
    {
        unset($this->loadedRelations[$relation]);
    }

    public function forgetRelations(): void
    {
        $this->loadedRelations = [];
    }

    /** @return list<string> */
    public function loadedRelations(): array
    {
        return array_keys($this->loadedRelations);
    }
}
