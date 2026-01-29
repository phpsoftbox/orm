<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Post;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\PostRepository;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class RefreshIntegrationTest extends TestCase
{
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
            "
                CREATE TABLE posts_rel (
                    id INTEGER PRIMARY KEY,
                    author_id INTEGER NOT NULL
                )
            "
        );

        $conn->execute(
            "
                INSERT INTO posts_rel (id, author_id) VALUES (1, 10)
            "
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());
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

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        $post = new Post(id: 1, authorId: 10);

        // Делаем сущность "без id" на уровне ORM-контракта, симулируя неперсистентный объект.
        // В реальном проекте это будет другая сущность/DTO, но нам важен контракт.
        $anonymous = new class($post) implements \PhpSoftBox\Orm\Contracts\EntityInterface {
            public function __construct(private Post $p) {}
            public function id(): ?int { return null; }
        };

        $this->expectException(\InvalidArgumentException::class);
        $em->refresh($anonymous);
    }
}
