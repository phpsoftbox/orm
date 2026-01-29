<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostBelongsTo;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\PostBelongsToRepository;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class BelongsToLoadIntegrationTest extends TestCase
{
    /**
     * Проверяет, что #[BelongsTo] является sugar над ManyToOne и корректно работает в:
     * - EntityManager::load()
     * - eager loading через repo->with(['author'])->all()
     */
    #[Test]
    public function loadsBelongsToRelation(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE authors (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                CREATE TABLE posts_rel (
                    id INTEGER PRIMARY KEY,
                    author_id INTEGER NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO authors (id, name) VALUES (10, 'Anton')
            ",
        );
        $conn->execute(
            '
                INSERT INTO posts_rel (id, author_id) VALUES (1, 10)
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        // В тестовом окружении подключаем "правильный" репозиторий,
        // чтобы был доступен eager loading через with([...]).
        $em->registerRepository(PostBelongsTo::class, new PostBelongsToRepository($conn, $em));

        // 1) load() на одной сущности
        $post = new PostBelongsTo(id: 1, authorId: 10);

        $em->load($post, 'author');

        self::assertNotNull($post->author);
        self::assertSame(10, $post->author->id);
        self::assertSame('Anton', $post->author->name);

        // 2) eager loading через with([...])
        $posts = $em->repository(PostBelongsTo::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $posts);

        /** @var AbstractEntityRepository<PostBelongsTo> $posts */
        $collection = $posts->with(['author'])->all();

        self::assertCount(1, $collection->all());
        self::assertNotNull($collection->all()[0]->author);
        self::assertSame('Anton', $collection->all()[0]->author->name);
    }
}
