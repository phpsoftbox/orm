<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithComments;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class HasManyIntegrationTest extends TestCase
{
    /**
     * Проверяет hasMany eager loading: repo->with(['comments'])->all()
     * должен заполнить свойство comments коллекцией дочерних сущностей.
     */
    #[Test]
    public function eagerLoadsHasMany(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            "
                CREATE TABLE posts_comments (
                    id INTEGER PRIMARY KEY,
                    title VARCHAR(255) NOT NULL
                )
            "
        );

        $conn->execute(
            "
                CREATE TABLE comments (
                    id INTEGER PRIMARY KEY,
                    post_id INTEGER NOT NULL,
                    body VARCHAR(255) NOT NULL
                )
            "
        );

        $conn->execute(
            "
                INSERT INTO posts_comments (id, title) VALUES (1, 'Hello')
            "
        );
        $conn->execute(
            "
                INSERT INTO comments (id, post_id, body) VALUES (10, 1, 'a')
            "
        );
        $conn->execute(
            "
                INSERT INTO comments (id, post_id, body) VALUES (11, 1, 'b')
            "
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        $repo = $em->repository(PostWithComments::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        /** @var AbstractEntityRepository<PostWithComments> $repo */
        $posts = $repo->with(['comments'])->all();

        $items = $posts->all();
        self::assertCount(1, $items);

        self::assertInstanceOf(EntityCollection::class, $items[0]->comments);
        self::assertCount(2, $items[0]->comments->all());
        self::assertSame(['a', 'b'], array_map(fn ($c) => $c->body, $items[0]->comments->all()));
    }
}
