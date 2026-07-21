<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\ChangeLog;

use PhpSoftBox\Orm\ChangeLog\EntityChangeAction;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContext;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeRecord;
use PhpSoftBox\Orm\ChangeLog\RoutingEntityChangeLogger;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\DummyEntityChangelogLogger;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\EntityWithChangelog;
use PhpSoftBox\Orm\Tests\Metadata\Fixtures\EntityWithDisabledChangelog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoutingEntityChangeLogger::class)]
final class RoutingEntityChangeLoggerTest extends TestCase
{
    #[Test]
    public function routesRecordToHandlerFromEntityMetadata(): void
    {
        $target = new class () implements EntityChangeLoggerInterface {
            /** @var list<EntityChangeRecord> */
            public array $records = [];

            public function log(EntityChangeRecord $record): void
            {
                $this->records[] = $record;
            }
        };
        $fallback = new class () implements EntityChangeLoggerInterface {
            /** @var list<EntityChangeRecord> */
            public array $records = [];

            public function log(EntityChangeRecord $record): void
            {
                $this->records[] = $record;
            }
        };

        $logger = new RoutingEntityChangeLogger(
            metadata: new AttributeMetadataProvider(),
            handlers: [
                DummyEntityChangelogLogger::class => $target,
            ],
            defaultLogger: $fallback,
        );

        $record = $this->recordFor(EntityWithChangelog::class);
        $logger->log($record);

        self::assertCount(1, $target->records);
        self::assertCount(0, $fallback->records);
    }

    #[Test]
    public function fallsBackToDefaultWhenEntityHandlerIsMissing(): void
    {
        $fallback = new class () implements EntityChangeLoggerInterface {
            /** @var list<EntityChangeRecord> */
            public array $records = [];

            public function log(EntityChangeRecord $record): void
            {
                $this->records[] = $record;
            }
        };

        $logger = new RoutingEntityChangeLogger(
            metadata: new AttributeMetadataProvider(),
            handlers: [],
            defaultLogger: $fallback,
        );

        $record = $this->recordFor(EntityWithChangelog::class);
        $logger->log($record);

        self::assertCount(1, $fallback->records);
    }

    #[Test]
    public function doesNotLogWhenChangelogIsDisabledAndDefaultLoggerIsNotConfigured(): void
    {
        $target = new class () implements EntityChangeLoggerInterface {
            /** @var list<EntityChangeRecord> */
            public array $records = [];

            public function log(EntityChangeRecord $record): void
            {
                $this->records[] = $record;
            }
        };

        $logger = new RoutingEntityChangeLogger(
            metadata: new AttributeMetadataProvider(),
            handlers: [
                DummyEntityChangelogLogger::class => $target,
            ],
        );

        $record = $this->recordFor(EntityWithDisabledChangelog::class);
        $logger->log($record);

        self::assertCount(0, $target->records);
    }

    /**
     * @param class-string $entityClass
     */
    private function recordFor(string $entityClass): EntityChangeRecord
    {
        return new EntityChangeRecord(
            action: EntityChangeAction::Update,
            entityClass: $entityClass,
            entityId: 1,
            before: ['name' => 'before'],
            after: ['name' => 'after'],
            changes: [['field' => 'name', 'before' => 'before', 'after' => 'after']],
            context: new EntityChangeContext(initiatorId: 10, initiatorType: 'user'),
        );
    }
}
