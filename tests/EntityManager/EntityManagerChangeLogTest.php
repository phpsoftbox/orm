<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeAction;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContext;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContextResolverInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeRecord;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Persistence\EntityPersisterInterface;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\AutoIdMappedEntity;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\GlobalIgnoredFieldsMappedEntity;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\StubUserMappedRepository;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\UserMappedEntity;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function is_array;

#[CoversClass(EntityManager::class)]
final class EntityManagerChangeLogTest extends TestCase
{
    private const REDACTED = '[redacted]';

    /**
     * Проверяет, что при CREATE changelog фиксирует фактический id после insert и не создает null->null diff.
     */
    #[Test]
    public function createWritesChangelogRecordWithActualIdAndWithoutNullToNullDiff(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('transaction')->willReturnCallback(static fn (callable $fn) => $fn());

        $persister = $this->createMock(EntityPersisterInterface::class);
        $persister->expects(self::once())->method('insert')->willReturnCallback(
            static function (object $entity, array $data): void {
                if ($entity instanceof AutoIdMappedEntity) {
                    $entity->id = 42;
                }
            },
        );
        $persister->expects(self::never())->method('update');
        $persister->expects(self::never())->method('delete');

        $logger = new class () implements EntityChangeLoggerInterface {
            /** @var list<EntityChangeRecord> */
            public array $records = [];

            public function log(EntityChangeRecord $record): void
            {
                $this->records[] = $record;
            }
        };

        $em = new EntityManager(
            connection: $connection,
            unitOfWork: new UnitOfWork(),
            persister: $persister,
            changeLogger: $logger,
        );

        $entity = new AutoIdMappedEntity(null, 'John');

        $em->registerRepository(AutoIdMappedEntity::class, new StubUserMappedRepository(new AutoIdMappedEntity(1, 'Stub')));
        $em->persist($entity);
        $em->flush();

        self::assertCount(1, $logger->records);

        $record = $logger->records[0];
        self::assertSame(EntityChangeAction::Create, $record->action);
        self::assertSame(AutoIdMappedEntity::class, $record->entityClass);
        self::assertSame(42, $record->entityId);
        self::assertSame(42, $this->findChange($record, 'id')['after'] ?? null);
        self::assertNull($this->findChange($record, 'deleted_datetime'));
    }

    /**
     * Проверяет, что при UPDATE формируется changelog с initiator-контекстом и diff по полям.
     */
    #[Test]
    public function updateWritesChangelogRecordWithFieldDiff(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('transaction')->willReturnCallback(static fn (callable $fn) => $fn());

        $persister = $this->createMock(EntityPersisterInterface::class);
        $persister->expects(self::once())->method('update');
        $persister->expects(self::never())->method('insert');
        $persister->expects(self::never())->method('delete');

        $logger = new class () implements EntityChangeLoggerInterface {
            /** @var list<EntityChangeRecord> */
            public array $records = [];

            public function log(EntityChangeRecord $record): void
            {
                $this->records[] = $record;
            }
        };

        $resolver = new class () implements EntityChangeContextResolverInterface {
            public function resolve(): EntityChangeContext
            {
                return new EntityChangeContext(initiatorId: 7, initiatorType: 'user', metadata: ['scope' => 'tenant']);
            }
        };

        $em = new EntityManager(
            connection: $connection,
            unitOfWork: new UnitOfWork(),
            persister: $persister,
            changeLogger: $logger,
            changeContextResolver: $resolver,
        );

        $entity = new UserMappedEntity(1, 'John');

        $em->registerRepository(UserMappedEntity::class, new StubUserMappedRepository($entity));

        /** @var UserMappedEntity $loaded */
        $loaded = $em->find(UserMappedEntity::class, 1);
        self::assertNotNull($loaded);

        $loaded->name = 'Kate';
        $em->persist($loaded);
        $em->flush();

        self::assertCount(1, $logger->records);

        $record = $logger->records[0];
        self::assertSame(EntityChangeAction::Update, $record->action);
        self::assertSame(UserMappedEntity::class, $record->entityClass);
        self::assertSame(1, $record->entityId);
        self::assertSame(7, $record->context->initiatorId);
        self::assertSame('user', $record->context->initiatorType);
        self::assertSame('tenant', $record->context->metadata['scope'] ?? null);
        self::assertSame('John', $this->findChange($record, 'name')['before'] ?? null);
        self::assertSame('Kate', $this->findChange($record, 'name')['after'] ?? null);
    }

    /**
     * Проверяет, что ошибка changelog-логгера не ломает flush write-path.
     */
    #[Test]
    public function loggerFailureDoesNotBreakFlush(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('transaction')->willReturnCallback(static fn (callable $fn) => $fn());

        $persister = $this->createMock(EntityPersisterInterface::class);
        $persister->expects(self::once())->method('update');

        $logger = new class () implements EntityChangeLoggerInterface {
            public function log(EntityChangeRecord $record): void
            {
                throw new RuntimeException('log failed');
            }
        };

        $em = new EntityManager(
            connection: $connection,
            unitOfWork: new UnitOfWork(),
            persister: $persister,
            changeLogger: $logger,
        );

        $entity = new UserMappedEntity(1, 'John');

        $em->registerRepository(UserMappedEntity::class, new StubUserMappedRepository($entity));

        /** @var UserMappedEntity $loaded */
        $loaded = $em->find(UserMappedEntity::class, 1);
        self::assertNotNull($loaded);

        $loaded->name = 'Kate';
        $em->persist($loaded);
        $em->flush();

        self::assertTrue(true);
    }

    /**
     * Проверяет, что ignore-поля из #[Changelog] не попадают в changelog в открытом виде.
     */
    #[Test]
    public function attributeIgnoredFieldsAreRedactedInChangelog(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('transaction')->willReturnCallback(static fn (callable $fn) => $fn());

        $persister = $this->createMock(EntityPersisterInterface::class);
        $persister->expects(self::once())->method('update');

        $logger = new class () implements EntityChangeLoggerInterface {
            /** @var list<EntityChangeRecord> */
            public array $records = [];

            public function log(EntityChangeRecord $record): void
            {
                $this->records[] = $record;
            }
        };

        $em = new EntityManager(
            connection: $connection,
            unitOfWork: new UnitOfWork(),
            persister: $persister,
            changeLogger: $logger,
        );

        $entity = new UserMappedEntity(1, 'John', 'old-secret');

        $em->registerRepository(UserMappedEntity::class, new StubUserMappedRepository($entity));

        /** @var UserMappedEntity $loaded */
        $loaded = $em->find(UserMappedEntity::class, 1);
        self::assertNotNull($loaded);

        $loaded->name     = 'Kate';
        $loaded->password = 'new-secret';
        $em->persist($loaded);
        $em->flush();

        self::assertCount(1, $logger->records);

        $record = $logger->records[0];
        self::assertSame(self::REDACTED, $record->before['password'] ?? null);
        self::assertSame(self::REDACTED, $record->after['password'] ?? null);

        $passwordChange = $this->findChange($record, 'password');
        self::assertNotNull($passwordChange);
        self::assertSame(self::REDACTED, $passwordChange['before']);
        self::assertSame(self::REDACTED, $passwordChange['after']);

        self::assertSame('John', $this->findChange($record, 'name')['before'] ?? null);
        self::assertSame('Kate', $this->findChange($record, 'name')['after'] ?? null);
    }

    /**
     * Проверяет, что глобальный DI-ignore список маскирует только указанные поля.
     */
    #[Test]
    public function globalIgnoredFieldsFromDiMaskSpecifiedFieldsOnly(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('transaction')->willReturnCallback(static fn (callable $fn) => $fn());

        $persister = $this->createMock(EntityPersisterInterface::class);
        $persister->expects(self::once())->method('update');

        $logger = new class () implements EntityChangeLoggerInterface {
            /** @var list<EntityChangeRecord> */
            public array $records = [];

            public function log(EntityChangeRecord $record): void
            {
                $this->records[] = $record;
            }
        };

        $em = new EntityManager(
            connection: $connection,
            unitOfWork: new UnitOfWork(),
            persister: $persister,
            changeLogger: $logger,
            changelogIgnoredFields: [' token '],
        );

        $entity = new GlobalIgnoredFieldsMappedEntity(1, 'John', 'old-token');

        $em->registerRepository(GlobalIgnoredFieldsMappedEntity::class, new StubUserMappedRepository($entity));

        /** @var GlobalIgnoredFieldsMappedEntity $loaded */
        $loaded = $em->find(GlobalIgnoredFieldsMappedEntity::class, 1);
        self::assertNotNull($loaded);

        $loaded->name  = 'Kate';
        $loaded->token = 'new-token';
        $em->persist($loaded);
        $em->flush();

        self::assertCount(1, $logger->records);

        $record = $logger->records[0];
        self::assertSame(self::REDACTED, $record->before['token'] ?? null);
        self::assertSame(self::REDACTED, $record->after['token'] ?? null);

        $tokenChange = $this->findChange($record, 'token');
        self::assertNotNull($tokenChange);
        self::assertSame(self::REDACTED, $tokenChange['before']);
        self::assertSame(self::REDACTED, $tokenChange['after']);

        self::assertSame('John', $record->before['name'] ?? null);
        self::assertSame('Kate', $record->after['name'] ?? null);

        $nameChange = $this->findChange($record, 'name');
        self::assertNotNull($nameChange);
        self::assertSame('John', $nameChange['before']);
        self::assertSame('Kate', $nameChange['after']);
    }

    /**
     * @return array{field: string, before: mixed, after: mixed}|null
     */
    private function findChange(EntityChangeRecord $record, string $field): ?array
    {
        foreach ($record->changes as $change) {
            if (!is_array($change)) {
                continue;
            }

            if (($change['field'] ?? null) !== $field) {
                continue;
            }

            return $change;
        }

        return null;
    }
}
