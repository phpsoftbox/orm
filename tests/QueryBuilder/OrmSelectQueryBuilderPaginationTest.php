<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\QueryBuilder\OrmSelectQueryBuilder;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\ContactEntity;
use PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures\FixedPaginationContext;
use PhpSoftBox\Pagination\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrmSelectQueryBuilder::class)]
#[CoversMethod(OrmSelectQueryBuilder::class, 'paginateEntities')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'paginationContext')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'usePaginator')]
final class OrmSelectQueryBuilderPaginationTest extends TestCase
{
    /**
     * Проверим, что paginateEntities() берёт страницу, perPage, path и query из контекста пагинации
     * и сохраняет их в ссылках.
     *
     * @see OrmSelectQueryBuilder::paginationContext()
     * @see OrmSelectQueryBuilder::paginateEntities()
     */
    #[Test]
    public function paginateEntitiesUsesPaginationContext(): void
    {
        $em = new EntityManager(connection: $this->connection());

        $result = $em->queryFor(ContactEntity::class)
            ->orderBy('id')
            ->paginationContext(new FixedPaginationContext(
                path: '/contacts',
                query: ['status' => 'active', 'page' => '2'],
                page: 2,
                perPage: 1,
            ))
            ->paginateEntities();

        self::assertCount(1, $result->data());
        self::assertSame(2, $result->data()[0]->id);
        self::assertSame('/contacts', $result->meta()['path']);
        self::assertStringStartsWith('/contacts?', $result->links()['next']);
        self::assertStringContainsString('status=active', $result->links()['next']);
        self::assertStringContainsString('page=3', $result->links()['next']);
    }

    /**
     * Проверим, что paginateEntities() использует переданный Paginator (path и дополнительные query-параметры).
     *
     * @see OrmSelectQueryBuilder::usePaginator()
     * @see OrmSelectQueryBuilder::paginateEntities()
     */
    #[Test]
    public function paginateEntitiesUsesConfiguredPaginator(): void
    {
        $em = new EntityManager(connection: $this->connection());

        $result = $em->queryFor(ContactEntity::class)
            ->orderBy('id')
            ->usePaginator(new Paginator()->path('/admin/contacts')->appends(['q' => 'anton']))
            ->paginateEntities(page: 1, perPage: 2);

        self::assertCount(2, $result->data());
        self::assertStringStartsWith('/admin/contacts?', $result->links()['next']);
        self::assertStringContainsString('q=anton', $result->links()['next']);
    }

    private function connection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute('CREATE TABLE contacts (id INTEGER PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL)');
        $conn->execute(
            '
                INSERT INTO contacts (id, name, email)
                VALUES
                    (1, \'Anton\', \'a@example.com\'),
                    (2, \'Maria\', \'m@example.com\'),
                    (3, \'Ivan\', \'i@example.com\')
            ',
        );

        return $conn;
    }
}
