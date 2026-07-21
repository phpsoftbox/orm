<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\ChangeLog;

use DateTimeImmutable;
use PhpSoftBox\Orm\ChangeLog\Driver\MongoEntityChangeLogger;
use PhpSoftBox\Orm\ChangeLog\EntityChangeAction;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContext;
use PhpSoftBox\Orm\ChangeLog\EntityChangeRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MongoEntityChangeLogger::class)]
final class MongoEntityChangeLoggerTest extends TestCase
{
    #[Test]
    public function logWritesDocumentIntoMongoCollection(): void
    {
        $collection = new class () {
            /** @var list<array<string, mixed>> */
            public array $documents = [];

            /**
             * @param array<string, mixed> $document
             */
            public function insertOne(array $document): void
            {
                $this->documents[] = $document;
            }
        };

        $manager = new class ($collection) {
            /** @var list<string> */
            public array $collections = [];

            /**
             * @param object{insertOne: callable} $collection
             */
            public function __construct(
                private readonly object $collection,
            ) {
            }

            public function collection(string $collection, string $connection): object
            {
                $this->collections[] = $connection . ':' . $collection;

                return $this->collection;
            }
        };

        $logger = new MongoEntityChangeLogger(
            mongo: $manager,
            collection: 'audit_log',
            connection: 'tenant',
            isMongoExtensionAvailable: true,
        );

        $logger->log(new EntityChangeRecord(
            action: EntityChangeAction::Update,
            entityClass: 'App\\Entity\\User',
            entityId: 10,
            before: ['name' => 'before'],
            after: ['name' => 'after'],
            changes: [['field' => 'name', 'before' => 'before', 'after' => 'after']],
            context: new EntityChangeContext(initiatorId: 1, initiatorType: 'system'),
            occurredAt: new DateTimeImmutable('2026-01-01 10:00:00+00:00'),
        ));

        self::assertSame(['tenant:audit_log'], $manager->collections);
        self::assertCount(1, $collection->documents);
        self::assertSame('App\\Entity\\User', $collection->documents[0]['entity_class']);
        self::assertSame('update', $collection->documents[0]['action']);
    }

    #[Test]
    public function logThrowsWhenManagerDoesNotProvideCollectionMethod(): void
    {
        $logger = new MongoEntityChangeLogger(mongo: new class () {
        }, isMongoExtensionAvailable: true);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('collection(string, string) method');

        $logger->log($this->record());
    }

    #[Test]
    public function logThrowsWhenMongoExtensionIsUnavailable(): void
    {
        $manager = new class () {
            public function collection(string $collection, string $connection): object
            {
                return new class () {
                    /**
                     * @param array<string, mixed> $document
                     */
                    public function insertOne(array $document): void
                    {
                    }
                };
            }
        };

        $logger = new MongoEntityChangeLogger(mongo: $manager, isMongoExtensionAvailable: false);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('requires ext-mongodb');

        $logger->log($this->record());
    }

    private function record(): EntityChangeRecord
    {
        return new EntityChangeRecord(
            action: EntityChangeAction::Update,
            entityClass: 'App\\Entity\\User',
            entityId: 1,
            before: ['name' => 'before'],
            after: ['name' => 'after'],
            changes: [['field' => 'name', 'before' => 'before', 'after' => 'after']],
            context: new EntityChangeContext(initiatorId: 1, initiatorType: 'system'),
            occurredAt: new DateTimeImmutable('2026-01-01 10:00:00+00:00'),
        );
    }
}
