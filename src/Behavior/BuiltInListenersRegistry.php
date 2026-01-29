<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use PhpSoftBox\Orm\Behavior\Listener\SluggableListener;
use PhpSoftBox\Orm\Contracts\BuiltInListenersRegistryInterface;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;

/**
 * Реестр встроенных listeners ORM.
 *
 * Нужен для:
 * - конфигурируемости EntityManager (можно отключить/заменить)
 * - DI-совместимости (можно сконструировать через контейнер)
 */
final readonly class BuiltInListenersRegistry implements BuiltInListenersRegistryInterface
{
    public function __construct(
        private MetadataProviderInterface $metadata,
    ) {
    }

    public function listeners(): array
    {
        return [
            new SluggableListener($this->metadata),
        ];
    }
}

