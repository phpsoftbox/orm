<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\ChangeLog;

use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use PhpSoftBox\Orm\ChangeLog\ChangeLogDocumentBuilder;
use PhpSoftBox\Orm\ChangeLog\EntityChangeAction;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContext;
use PhpSoftBox\Orm\ChangeLog\EntityChangeRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

use function class_exists;

#[CoversClass(ChangeLogDocumentBuilder::class)]
final class ChangeLogDocumentBuilderTest extends TestCase
{
    #[Test]
    public function buildCreatesMongoDocumentAndNormalizesPayload(): void
    {
        $builder = new ChangeLogDocumentBuilder();

        $record = new EntityChangeRecord(
            action: EntityChangeAction::Update,
            entityClass: 'App\\Entity\\User',
            entityId: 123,
            before: ['updated_at' => new DateTimeImmutable('2026-01-01 10:00:00+00:00')],
            after: ['updated_at' => new DateTimeImmutable('2026-01-01 10:01:00+00:00')],
            changes: [['field' => 'meta', 'before' => new stdClass(), 'after' => null]],
            context: new EntityChangeContext(
                initiatorId: '42',
                initiatorType: 'user',
                metadata: [
                    'request_time' => new DateTimeImmutable('2026-01-01 10:00:30+00:00'),
                    'label'        => new class () implements Stringable {
                        public function __toString(): string
                        {
                            return 'import-request';
                        }
                    },
                ],
            ),
            occurredAt: new DateTimeImmutable('2026-01-01 10:02:00+00:00'),
        );

        $document = $builder->build($record);

        self::assertSame('App\\Entity\\User', $document['entity_class']);
        self::assertSame(123, $document['entity_id']);
        self::assertSame('update', $document['action']);
        self::assertSame('2026-01-01T10:02:00+00:00', $document['occurred_at_iso']);
        if (class_exists(UTCDateTime::class)) {
            self::assertInstanceOf(UTCDateTime::class, $document['occurred_at']);
        } else {
            self::assertIsString($document['occurred_at']);
        }

        self::assertIsArray($document['before']);
        self::assertIsArray($document['after']);
        if (class_exists(UTCDateTime::class)) {
            self::assertInstanceOf(UTCDateTime::class, $document['before']['updated_at']);
            self::assertInstanceOf(UTCDateTime::class, $document['after']['updated_at']);
        } else {
            self::assertIsString($document['before']['updated_at']);
            self::assertIsString($document['after']['updated_at']);
        }

        self::assertIsArray($document['changes']);
        self::assertIsArray($document['changes'][0]);
        self::assertSame('[unsupported:stdClass]', $document['changes'][0]['before']);

        self::assertIsArray($document['initiator']);
        self::assertSame('42', $document['initiator']['id']);
        self::assertSame('user', $document['initiator']['type']);
        self::assertIsArray($document['initiator']['metadata']);
        self::assertSame('import-request', $document['initiator']['metadata']['label']);
        if (class_exists(UTCDateTime::class)) {
            self::assertInstanceOf(UTCDateTime::class, $document['initiator']['metadata']['request_time']);
        } else {
            self::assertIsString($document['initiator']['metadata']['request_time']);
        }
    }
}
