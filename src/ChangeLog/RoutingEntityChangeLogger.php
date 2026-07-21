<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\ChangeLog;

use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use Throwable;

use function is_string;
use function trim;

final readonly class RoutingEntityChangeLogger implements EntityChangeLoggerInterface
{
    /**
     * @param array<class-string<EntityChangeLoggerInterface>, EntityChangeLoggerInterface> $handlers
     */
    public function __construct(
        private MetadataProviderInterface $metadata,
        private array $handlers,
        private ?EntityChangeLoggerInterface $defaultLogger = null,
    ) {
    }

    public function log(EntityChangeRecord $record): void
    {
        $logger = $this->resolveLoggerForEntityClass($record->entityClass);
        if (!$logger instanceof EntityChangeLoggerInterface) {
            return;
        }

        $logger->log($record);
    }

    private function resolveLoggerForEntityClass(string $entityClass): ?EntityChangeLoggerInterface
    {
        try {
            $classMetadata = $this->metadata->for($entityClass);
        } catch (Throwable) {
            return $this->defaultLogger;
        }

        $changelog = $classMetadata->changelog;
        if ($changelog === null || !$changelog->enabled) {
            return $this->defaultLogger;
        }

        $handlerClass = is_string($changelog->logHandler) ? trim($changelog->logHandler) : '';
        if ($handlerClass === '') {
            return $this->defaultLogger;
        }

        $handler = $this->handlers[$handlerClass] ?? null;
        if ($handler instanceof EntityChangeLoggerInterface) {
            return $handler;
        }

        return $this->defaultLogger;
    }
}
