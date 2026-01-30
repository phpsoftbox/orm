<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\IdentityMap;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\IdentityMapInterface;
use PhpSoftBox\Orm\Identity\EntityKey;
use WeakReference;

final class WeakIdentityMap implements IdentityMapInterface
{
    /**
     * @var array<string, WeakReference>
     */
    private array $items = [];

    public function get(EntityKey $key): ?EntityInterface
    {
        $k = $key->toString();

        $ref = $this->items[$k] ?? null;
        if ($ref === null) {
            return null;
        }

        $entity = $ref->get();
        if (!$entity instanceof EntityInterface) {
            unset($this->items[$k]);

            return null;
        }

        return $entity;
    }

    public function set(EntityKey $key, EntityInterface $entity): void
    {
        $this->items[$key->toString()] = WeakReference::create($entity);
    }

    public function remove(EntityKey $key): void
    {
        unset($this->items[$key->toString()]);
    }

    public function clear(): void
    {
        $this->items = [];
    }
}
