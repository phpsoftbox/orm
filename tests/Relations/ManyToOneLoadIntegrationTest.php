<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Author;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Post;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class ManyToOneLoadIntegrationTest extends TestCase
{
    /**
     * Проверяет, что EntityManager::load() подгружает ManyToOne связь и записывает объект в свойство.
     */
    #[Test]
    public function loadsManyToOneRelation(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            "
                CREATE TABLE authors (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            "
        );

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
                INSERT INTO authors (id, name) VALUES (10, 'Anton')
            "
        );
        $conn->execute(
            "
                INSERT INTO posts_rel (id, author_id) VALUES (1, 10)
            "
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        // 1) load() на одной сущности
        $post = new Post(id: 1, authorId: 10);
        $em->load($post, 'author');

        self::assertNotNull($post->author);
        self::assertSame(10, $post->author->id);
        self::assertSame('Anton', $post->author->name);

        // 2) eager loading через with([...])
        $posts = $em->repository(Post::class);
        self::assertInstanceOf(\PhpSoftBox\Orm\Repository\AbstractEntityRepository::class, $posts);

        /** @var \PhpSoftBox\Orm\Repository\AbstractEntityRepository<Post> $posts */
        $collection = $posts->with(['author'])->all();

        self::assertCount(1, $collection->all());
        self::assertNotNull($collection->all()[0]->author);
        self::assertSame('Anton', $collection->all()[0]->author->name);
    }
}
