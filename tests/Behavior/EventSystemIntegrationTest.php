<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior;

use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\Behavior\Attributes\EventListener;
use PhpSoftBox\Orm\Behavior\DefaultEventDispatcher;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\GenericEntityRepository;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\EventEntity;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\EventEntityListener;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class EventSystemIntegrationTest extends TestCase
{
    /**
     * Проверяет, что:
     * - хуки #[Hook] вызываются
     * - listeners #[EventListener] + #[Listen] вызываются
     * - изменения через state()->register() реально попадают в INSERT.
     */
    #[Test]
    public function hooksAndListenersCanMutateInsertData(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            "
                CREATE TABLE event_entities (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL
                )
            "
        );

        $dispatcher = new DefaultEventDispatcher([new EventEntityListener()]);

        $em = new EntityManager(
            connection: $conn,
            unitOfWork: new InMemoryUnitOfWork(),
            events: $dispatcher,
        );

        $em->registerRepository(EventEntity::class, new GenericEntityRepository($conn, EventEntity::class));

        $em->persist(new EventEntity(name: 'original'));
        $em->flush();

        $row = $conn->fetchOne('SELECT name FROM event_entities LIMIT 1');
        self::assertNotNull($row);

        // listener должен перезаписать hook (listener сработает на OnCreate в dispatcher)
        self::assertSame('from_listener', $row['name']);
    }
}
