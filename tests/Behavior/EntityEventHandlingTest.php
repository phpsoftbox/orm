<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\Behavior\ContainerListenerResolver;
use PhpSoftBox\Orm\Behavior\DefaultListenerResolver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Exception\OrmException;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\ArrayContainer;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\DependentListener;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\DependentListenerEntity;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\ListenedPost;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\ListenerNameSource;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\RecordingEntityListener;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\ThrowingHookEntity;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\UnlistenedTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(EntityManager::class)]
#[CoversClass(DefaultListenerResolver::class)]
#[CoversClass(ContainerListenerResolver::class)]
#[CoversMethod(EntityManager::class, 'flush')]
#[CoversMethod(DefaultListenerResolver::class, 'resolve')]
#[CoversMethod(ContainerListenerResolver::class, 'resolve')]
final class EntityEventHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        RecordingEntityListener::$entities = [];
    }

    /**
     * Проверим, что исключение из #[Hook] пробрасывается из flush() и откатывает INSERT.
     *
     * @see EntityManager::flush()
     */
    #[Test]
    public function hookExceptionAbortsFlush(): void
    {
        $conn = $this->connection('hook_entities');
        $em   = new EntityManager(connection: $conn);

        $em->persist(new ThrowingHookEntity(name: 'first'));

        try {
            $em->flush();
            self::fail('Исключение hook должно быть проброшено.');
        } catch (RuntimeException $e) {
            self::assertSame('Hook failed.', $e->getMessage());
        }

        // Запись не должна появиться: транзакция flush() откатилась.
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) AS c FROM hook_entities')['c']);
    }

    /**
     * Проверим, что listener с обязательными зависимостями не игнорируется молча:
     * DefaultListenerResolver бросает понятное исключение, flush() его пробрасывает.
     *
     * @see DefaultListenerResolver::resolve()
     * @see EntityManager::flush()
     */
    #[Test]
    public function listenerWithDependenciesFailsWithDefaultResolver(): void
    {
        $conn = $this->connection('dependent_listener_entities');
        $em   = new EntityManager(connection: $conn);

        $em->persist(new DependentListenerEntity(name: 'original'));

        $this->expectException(OrmException::class);
        $this->expectExceptionMessage(DependentListener::class);

        $em->flush();
    }

    /**
     * Проверим, что ContainerListenerResolver берёт listener сущности из контейнера
     * и listener с зависимостями реально вызывается.
     *
     * @see ContainerListenerResolver::resolve()
     * @see EntityManager::flush()
     */
    #[Test]
    public function containerResolverProvidesListenerWithDependencies(): void
    {
        $conn = $this->connection('dependent_listener_entities');

        $container = new ArrayContainer([
            DependentListener::class => new DependentListener(new ListenerNameSource('from_container')),
        ]);

        $em = new EntityManager(
            connection: $conn,
            listenerResolver: new ContainerListenerResolver($container),
        );

        $em->persist(new DependentListenerEntity(name: 'original'));
        $em->flush();

        self::assertSame('from_container', $conn->fetchOne('SELECT name FROM dependent_listener_entities')['name']);
    }

    /**
     * Проверим, что #[EventListener] одной сущности не получает события другой сущности,
     * даже после того как listener уже был создан.
     *
     * @see EntityManager::flush()
     */
    #[Test]
    public function entityListenerReceivesOnlyEventsOfItsEntity(): void
    {
        $conn = $this->connection('listened_posts');
        $conn->execute('CREATE TABLE unlistened_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL)');

        $em = new EntityManager(connection: $conn);

        // Сначала событие сущности с listener'ом (listener создаётся), затем — сущности без listener'а.
        $em->persist(new ListenedPost(name: 'post'));
        $em->flush();

        $em->persist(new UnlistenedTag(name: 'tag'));
        $em->flush();

        self::assertSame([ListenedPost::class], RecordingEntityListener::$entities);
    }

    private function connection(string $table): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL)');

        return $conn;
    }
}
