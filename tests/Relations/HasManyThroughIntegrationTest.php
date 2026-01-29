<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\CompanyWithPostsThroughAuthors;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostForThrough;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\CompanyWithPostsThroughAuthorsRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\PostForThroughRepository;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(EntityManager::class)]
final class HasManyThroughIntegrationTest extends TestCase
{
    /**
     * Проверяет eager loading связи hasManyThrough через repo->with(['posts'])->all().
     */
    #[Test]
    public function eagerLoadsHasManyThrough(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE companies_hmt (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE authors_hmt (
                    id INTEGER PRIMARY KEY,
                    company_id INTEGER NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE posts_hmt (
                    id INTEGER PRIMARY KEY,
                    author_id INTEGER NOT NULL,
                    title VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO companies_hmt (id, name) VALUES (1, 'Mindgarden')
            ",
        );
        $conn->execute(
            '
                INSERT INTO authors_hmt (id, company_id) VALUES (10, 1)
            ',
        );
        $conn->execute(
            '
                INSERT INTO authors_hmt (id, company_id) VALUES (11, 1)
            ',
        );
        $conn->execute(
            "
                INSERT INTO posts_hmt (id, author_id, title) VALUES (100, 10, 'a')
            ",
        );
        $conn->execute(
            "
                INSERT INTO posts_hmt (id, author_id, title) VALUES (101, 11, 'b')
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());

        $em->registerRepository(CompanyWithPostsThroughAuthors::class, new CompanyWithPostsThroughAuthorsRepository($conn, $em));
        $em->registerRepository(PostForThrough::class, new PostForThroughRepository($conn, $em));

        $repo = $em->repository(CompanyWithPostsThroughAuthors::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        /** @var AbstractEntityRepository<CompanyWithPostsThroughAuthors> $repo */
        $companies = $repo->with(['posts'])->all();
        $items     = $companies->all();

        self::assertCount(1, $items);
        self::assertCount(2, $items[0]->posts->all());
        self::assertSame(['a', 'b'], array_map(fn (PostForThrough $p) => $p->title, $items[0]->posts->all()));
    }
}
