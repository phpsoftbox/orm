<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use InvalidArgumentException;
use PDO;
use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\RefreshCastedEntity;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\RefreshStatus;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Post;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\PostRepository;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class RefreshIntegrationTest extends TestCase
{
    #[Test]
    public function refreshUsesRepositoryHydrationAndKeepsManagedIdentity(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE refresh_casted_entities (
                    id INTEGER PRIMARY KEY,
                    is_enabled INTEGER NOT NULL,
                    created_datetime VARCHAR(64) NULL,
                    status VARCHAR(32) NOT NULL,
                    payload TEXT NOT NULL,
                    external_id VARCHAR(36) NULL,
                    note VARCHAR(255) NULL
                )
            ',
        );
        $conn->execute(
            "
                INSERT INTO refresh_casted_entities (
                    id, is_enabled, created_datetime, status, payload, external_id, note
                ) VALUES (
                    1, 0, NULL, 'disabled', '{}', NULL, 'before'
                )
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $entity = $em->find(RefreshCastedEntity::class, 1);

        self::assertInstanceOf(RefreshCastedEntity::class, $entity);
        self::assertFalse($entity->isEnabled);
        self::assertNull($entity->createdDatetime);
        self::assertSame(RefreshStatus::Disabled, $entity->status);
        self::assertSame([], $entity->payload);
        self::assertNull($entity->externalId);
        self::assertSame('before', $entity->note);

        $uuid = '018f7f55-4d61-7c2a-9ae2-8f70f62e6352';

        $conn->execute(
            '
                UPDATE refresh_casted_entities
                SET is_enabled = :is_enabled,
                    created_datetime = :created_datetime,
                    status = :status,
                    payload = :payload,
                    external_id = :external_id,
                    note = :note
                WHERE id = :id
            ',
            [
                'is_enabled'       => 1,
                'created_datetime' => '2026-08-17 12:30:00',
                'status'           => 'active',
                'payload'          => '{"items":[1,2]}',
                'external_id'      => $uuid,
                'note'             => null,
                'id'               => 1,
            ],
        );

        $em->refresh($entity);

        self::assertSame($entity, $em->find(RefreshCastedEntity::class, 1));
        self::assertTrue($entity->isEnabled);
        self::assertInstanceOf(DatePoint::class, $entity->createdDatetime);
        self::assertSame('2026-08-17 12:30:00', $entity->createdDatetime->format('Y-m-d H:i:s'));
        self::assertSame(RefreshStatus::Active, $entity->status);
        self::assertSame(['items' => [1, 2]], $entity->payload);
        self::assertSame($uuid, $entity->externalId?->toString());
        self::assertNull($entity->note);

        $snapshot = $em->unitOfWork()->snapshot($entity);
        self::assertNotNull($snapshot);
        self::assertTrue($snapshot->data['is_enabled']);
        self::assertSame('2026-08-17 12:30:00', $snapshot->data['created_datetime']);
        self::assertSame('active', $snapshot->data['status']);
        self::assertSame('{"items":[1,2]}', $snapshot->data['payload']);
        self::assertSame($uuid, $snapshot->data['external_id']);
        self::assertNull($snapshot->data['note']);
    }

    /**
     * Проверяет, что refresh() перезагружает поля сущности из БД в текущий объект,
     * тем самым сбрасывая локальные изменения.
     */
    #[Test]
    public function refreshReloadsEntityStateFromDatabase(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE posts_rel (
                    id INTEGER PRIMARY KEY,
                    author_id INTEGER NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                INSERT INTO posts_rel (id, author_id) VALUES (1, 10)
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $em->registerRepository(Post::class, new PostRepository($conn, $em));

        $post = $em->find(Post::class, 1);
        self::assertNotNull($post);
        self::assertSame(10, $post->authorId);

        // Локальная мутация (не в БД)
        $post->authorId = 999;
        self::assertSame(999, $post->authorId);

        // refresh() должен вернуть состояние из БД
        $em->refresh($post);
        self::assertSame(10, $post->authorId);
    }

    /**
     * Проверяет, что refresh() бросает исключение для сущности без id.
     */
    #[Test]
    public function refreshThrowsForEntityWithoutId(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $post = new Post(id: 1, authorId: 10);

        // Делаем сущность "без id" на уровне ORM-контракта, симулируя неперсистентный объект.
        // В реальном проекте это будет другая сущность/DTO, но нам важен контракт.
        $anonymous = new class ($post) implements EntityInterface {
            public function __construct(
                private Post
            $p,
            ) {
            }
            public function id(): ?int
            {
                return null;
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $em->refresh($anonymous);
    }
}
