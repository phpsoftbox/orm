<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithNestedAuthor;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class NestedWithIntegrationTest extends TestCase
{
    /**
     * Проверяет nested eager loading через dotted-path: with(['author.company']).
     */
    #[Test]
    public function withSupportsNestedRelations(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE companies (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE authors2 (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    company_id INTEGER NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE posts_nested (
                    id INTEGER PRIMARY KEY,
                    author_id INTEGER NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO companies (id, name) VALUES (1, 'Mindgarden')
            ",
        );
        $conn->execute(
            "
                INSERT INTO authors2 (id, name, company_id) VALUES (10, 'Anton', 1)
            ",
        );
        $conn->execute(
            '
                INSERT INTO posts_nested (id, author_id) VALUES (100, 10)
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        $repo = $em->repository(PostWithNestedAuthor::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        /** @var AbstractEntityRepository<PostWithNestedAuthor> $repo */
        $posts = $repo->with(['author.company'])->all();

        $items = $posts->all();
        self::assertCount(1, $items);
        self::assertNotNull($items[0]->author);
        self::assertNotNull($items[0]->author->company);
        self::assertSame('Mindgarden', $items[0]->author->company->name);
    }
}
