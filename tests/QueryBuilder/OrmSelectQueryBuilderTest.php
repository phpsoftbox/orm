<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder;

use InvalidArgumentException;
use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\PostgresDriver;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Database\QueryBuilder\Expression;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\QueryBuilder\OrmSelectQueryBuilder;
use PhpSoftBox\Orm\Result\EntityResult;
use PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures\AmbiguousSearchableProduct;
use PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures\MorphLog;
use PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures\SearchableProduct;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithComments;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithVisibleComments;
use PhpSoftBox\Orm\Tests\Repository\Fixtures\SoftDeleteEntity;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrmSelectQueryBuilder::class)]
#[CoversMethod(OrmSelectQueryBuilder::class, 'withDeleted')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'onlyDeleted')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'fetchEntities')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'fetchEntity')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'fetchEntityResults')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'paginateEntities')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'paginateEntityResults')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'exists')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'selectRaw')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'from')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'withCount')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'withExists')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'withAggregate')]
#[CoversClass(EntityResult::class)]
final class OrmSelectQueryBuilderTest extends TestCase
{
    /**
     * Проверяет применение soft delete scope при выборке сущностей.
     */
    #[Test]
    public function testSoftDeleteScopes(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES
                    (1, 'Alive', NULL),
                    (2, 'Deleted', '2026-01-01T00:00:00+00:00')
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $default = $em->queryFor(SoftDeleteEntity::class)->fetchEntities();
        $this->assertCount(1, $default->all());

        $withDeleted = $em->queryFor(SoftDeleteEntity::class)->withDeleted()->fetchEntities();
        $this->assertCount(2, $withDeleted->all());

        $onlyDeleted = $em->queryFor(SoftDeleteEntity::class)->onlyDeleted()->fetchEntities();
        $this->assertCount(1, $onlyDeleted->all());
        $this->assertSame(2, $onlyDeleted->first()?->id);
    }

    /**
     * Проверяет, что fetchEntity() регистрирует snapshot в UnitOfWork.
     * Это нужно для корректного dirty-checking/changelog diff при последующем persist+flush.
     */
    #[Test]
    public function testFetchEntityRegistersSnapshotInUnitOfWork(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES (1, 'Alive', NULL)
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $entity = $em->queryFor(SoftDeleteEntity::class)
            ->where('id = :id', ['id' => 1])
            ->fetchEntity();

        $this->assertInstanceOf(SoftDeleteEntity::class, $entity);
        $this->assertNotNull($em->unitOfWork()->snapshot($entity));
    }

    #[Test]
    public function testExistsRespectsSoftDeleteScopes(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES
                    (1, 'Alive', NULL),
                    (2, 'Deleted', '2026-01-01T00:00:00+00:00')
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $this->assertFalse(
            $em->queryFor(SoftDeleteEntity::class)
                ->where('id = :id', ['id' => 2])
                ->exists(),
        );

        $this->assertTrue(
            $em->queryFor(SoftDeleteEntity::class)
                ->withDeleted()
                ->where('id = :id', ['id' => 2])
                ->exists(),
        );

        $this->assertTrue(
            $em->queryFor(SoftDeleteEntity::class)
                ->onlyDeleted()
                ->where('id = :id', ['id' => 2])
                ->exists(),
        );

        $this->assertFalse(
            $em->queryFor(SoftDeleteEntity::class)
                ->onlyDeleted()
                ->where('id = :id', ['id' => 1])
                ->exists(),
        );
    }

    #[Test]
    public function testFromRejectsNonRootTable(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot switch from');

        $em->queryFor(SoftDeleteEntity::class)->from('some_other_table t');
    }

    #[Test]
    public function testFromRejectsExpression(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('supports only the root entity table in from()');

        $em->queryFor(SoftDeleteEntity::class)->from(new Expression('soft_delete_entities s'));
    }

    #[Test]
    public function testFromAllowsRootTableAlias(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES
                    (1, 'Alive', NULL),
                    (2, 'Deleted', '2026-01-01T00:00:00+00:00')
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $row = $em->queryFor(SoftDeleteEntity::class)
                    ->from('soft_delete_entities s')
                    ->where('s.id = :id', ['id' => 1])
                    ->fetchOne();

        $this->assertSame(1, $row['id'] ?? null);
    }

    #[Test]
    public function testSelectRawWorksInOrmQueryBuilder(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES
                    (1, 'alive', NULL),
                    (2, 'deleted', '2026-01-01T00:00:00+00:00')
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $row = $em->queryFor(SoftDeleteEntity::class)
            ->selectRaw('UPPER(name) AS upper_name')
            ->where('id = :id', ['id' => 1])
            ->fetchOne();

        $this->assertSame('ALIVE', $row['upper_name'] ?? null);
    }

    #[Test]
    public function testFetchEntityResultsReturnsEntitiesWithExtraColumns(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES
                    (1, 'Alive', NULL),
                    (2, 'Deleted', '2026-01-01T00:00:00+00:00')
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $results = $em->queryFor(SoftDeleteEntity::class)
            ->select('soft_delete_entities.*')
            ->addSelectRaw('LENGTH(name) AS name_length')
            ->orderBy('id', 'ASC')
            ->fetchEntityResults();

        $this->assertCount(1, $results);
        $this->assertInstanceOf(EntityResult::class, $results[0]);
        $this->assertInstanceOf(SoftDeleteEntity::class, $results[0]->entity);
        $this->assertSame(1, $results[0]->entity->id);
        $this->assertSame('Alive', $results[0]->entity->name);
        $this->assertSame(5, $results[0]->extra('name_length'));
        $this->assertSame(['name_length' => 5], $results[0]->extra);
    }

    #[Test]
    public function testWithCountReturnsRelationCountInEntityResultExtra(): void
    {
        $conn = $this->createPostsWithCommentsConnection();

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $results = $em->queryFor(PostWithComments::class)
            ->withCount('comments')
            ->orderBy('id', 'ASC')
            ->fetchEntityResults();

        $this->assertCount(2, $results);
        $this->assertInstanceOf(EntityResult::class, $results[0]);
        $this->assertSame(1, $results[0]->entity->id);
        $this->assertSame(1, $results[0]->id);
        $this->assertSame(3, $results[0]->extra('comments_count'));
        $this->assertSame(3, $results[0]->comments_count);
        $this->assertSame(3, $results[0]['comments_count']);
        $this->assertSame(0, $results[1]->extra('comments_count'));
    }

    #[Test]
    public function testWithCountRespectsRelationScope(): void
    {
        $conn = $this->createPostsWithCommentsConnection();

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $results = $em->queryFor(PostWithVisibleComments::class)
            ->withCount('visibleComments')
            ->fetchEntityResults();

        $this->assertCount(2, $results);
        $this->assertSame(2, $results[0]->extra('visible_comments_count'));
    }

    /**
     * Проверяет, что with* helpers не генерируют camelCase alias для PostgreSQL.
     */
    #[Test]
    public function testWithHelpersUseLowerSnakeCaseAliasesForCamelCaseRelations(): void
    {
        $conn = new Connection(new PDO('sqlite::memory:'), new PostgresDriver());

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $built = $em->queryFor(PostWithVisibleComments::class)
            ->withCount('visibleComments')
            ->withExists('visibleComments')
            ->withSum('visibleComments', 'likes')
            ->query()
            ->toSql();

        self::assertStringContainsString('agg_visible_comments_1', $built['sql']);
        self::assertStringContainsString('sub_visible_comments_2', $built['sql']);
        self::assertStringContainsString('agg_visible_comments_3', $built['sql']);
        self::assertStringNotContainsString('visibleComments', $built['sql']);
    }

    #[Test]
    public function testWithExistsAndAggregateHelpersReturnExtraColumns(): void
    {
        $conn = $this->createPostsWithCommentsConnection();

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $results = $em->queryFor(PostWithComments::class)
            ->withExists('comments')
            ->withSum('comments', 'likes')
            ->orderBy('id', 'ASC')
            ->fetchEntityResults();

        $this->assertCount(2, $results);
        $this->assertTrue((bool) $results[0]->extra('comments_exists'));
        $this->assertSame(8, $results[0]->extra('comments_likes_sum'));
        $this->assertFalse((bool) $results[1]->extra('comments_exists'));
        $this->assertNull($results[1]->extra('comments_likes_sum'));
    }

    #[Test]
    public function testWithExistsAndCountSupportMorphTo(): void
    {
        $conn = $this->createMorphLogConnection();

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $results = $em->queryFor(MorphLog::class)
            ->withExists('subject')
            ->withCount('subject')
            ->orderBy('id', 'ASC')
            ->fetchEntityResults();

        $this->assertCount(3, $results);
        $this->assertTrue((bool) $results[0]->extra('subject_exists'));
        $this->assertSame(1, $results[0]->extra('subject_count'));
        $this->assertFalse((bool) $results[1]->extra('subject_exists'));
        $this->assertSame(0, $results[1]->extra('subject_count'));
        $this->assertFalse((bool) $results[2]->extra('subject_exists'));
        $this->assertSame(0, $results[2]->extra('subject_count'));
    }

    #[Test]
    public function testWithAggregateRejectsMorphTo(): void
    {
        $conn = $this->createMorphLogConnection();

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('morph_to');

        $em->queryFor(MorphLog::class)->withSum('subject', 'id');
    }

    /**
     * Проверяет, что пагинация возвращает сущности.
     */
    #[Test]
    public function testPaginateEntitiesReturnsEntities(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES
                    (1, 'Alive', NULL),
                    (2, 'Alive 2', NULL),
                    (3, 'Alive 3', NULL)
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $pagination = $em->queryFor(SoftDeleteEntity::class)
            ->orderBy('id', 'ASC')
            ->paginateEntities(1, 2);

        $items = $pagination->data();

        $this->assertCount(2, $items);
        $this->assertInstanceOf(SoftDeleteEntity::class, $items[0]);
        $this->assertSame(1, $items[0]->id);
    }

    #[Test]
    public function testPaginateEntityResultsReturnsPaginatedExtraColumns(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE soft_delete_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    deleted_datetime VARCHAR(64) NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO soft_delete_entities (id, name, deleted_datetime)
                VALUES
                    (1, 'Alive', NULL),
                    (2, 'Alive 2', NULL),
                    (3, 'Alive 3', NULL)
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $pagination = $em->queryFor(SoftDeleteEntity::class)
            ->select('soft_delete_entities.*')
            ->addSelectRaw('LENGTH(name) AS name_length')
            ->orderBy('id', 'ASC')
            ->paginateEntityResults(1, 2);

        $items = $pagination->data();

        $this->assertCount(2, $items);
        $this->assertSame(3, $pagination->meta()['total'] ?? null);
        $this->assertInstanceOf(EntityResult::class, $items[0]);
        $this->assertInstanceOf(SoftDeleteEntity::class, $items[0]->entity);
        $this->assertSame(1, $items[0]->entity->id);
        $this->assertSame(5, $items[0]->extra('name_length'));
        $this->assertSame(7, $items[1]->extra('name_length'));
    }

    #[Test]
    public function testOrmFullTextSearchUsesDefaultProfile(): void
    {
        $conn = new Connection(new PDO('sqlite::memory:'), new PostgresDriver());

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $built = $em->queryFor(SearchableProduct::class)
            ->whereSearch('телефон')
            ->selectSearchRank('телефон')
            ->orderByPgFullTextRank()
            ->query()
            ->toSql();

        $this->assertSame(
            'SELECT "products".*, ts_rank_cd("products"."natural_search_vector", websearch_to_tsquery(\'russian\', :__pg_fts_query_2)) AS "search_rank" FROM "products" WHERE ("products"."natural_search_vector" @@ websearch_to_tsquery(\'russian\', :__pg_fts_query_1)) ORDER BY "search_rank" DESC',
            $built['sql'],
        );
        $this->assertSame(
            [
                '__pg_fts_query_1' => 'телефон',
                '__pg_fts_query_2' => 'телефон',
            ],
            $built['params'],
        );
    }

    #[Test]
    public function testOrmFullTextHeadlineUsesProfileOptions(): void
    {
        $conn = new Connection(new PDO('sqlite::memory:'), new PostgresDriver());

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $built = $em->queryFor(SearchableProduct::class)
            ->selectSearchHeadline('name', 'телефон', headlineOptions: 'StartSel=<mark>, StopSel=</mark>')
            ->query()
            ->toSql();

        $this->assertSame(
            'SELECT "products".*, ts_headline(\'russian\', "products"."name", websearch_to_tsquery(\'russian\', :__pg_fts_query_1), \'StartSel=<mark>, StopSel=</mark>\') AS "search_headline" FROM "products"',
            $built['sql'],
        );
        $this->assertSame(['__pg_fts_query_1' => 'телефон'], $built['params']);
    }

    #[Test]
    public function testOrmWhereAnySearchUsesEachProfileConfig(): void
    {
        $conn = new Connection(new PDO('sqlite::memory:'), new PostgresDriver());

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $built = $em->queryFor(SearchableProduct::class)
            ->whereAnySearch('sku-100', ['natural', 'technical'])
            ->query()
            ->toSql();

        $this->assertSame(
            'SELECT * FROM "products" WHERE (("products"."natural_search_vector" @@ websearch_to_tsquery(\'russian\', :__pg_fts_query_1)) OR ("products"."technical_search_vector" @@ plainto_tsquery(\'simple\', :__pg_fts_query_2)))',
            $built['sql'],
        );
        $this->assertSame(
            [
                '__pg_fts_query_1' => 'sku-100',
                '__pg_fts_query_2' => 'sku-100',
            ],
            $built['params'],
        );
    }

    #[Test]
    public function testOrmAggregateSelectResolvesEntityProperty(): void
    {
        $conn = new Connection(new PDO('sqlite::memory:'), new SqliteDriver());

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $built = $em->queryFor(SearchableProduct::class)
            ->selectCount('id', 'products_count')
            ->query()
            ->toSql();

        $this->assertSame(
            'SELECT "products".*, COUNT("products"."id") AS "products_count" FROM "products"',
            $built['sql'],
        );
    }

    #[Test]
    public function testOrmSearchWithoutDefaultProfileRequiresExplicitName(): void
    {
        $conn = new Connection(new PDO('sqlite::memory:'), new SqliteDriver());

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ambiguous');

        $em->queryFor(AmbiguousSearchableProduct::class)->whereSearch('телефон');
    }

    private function createPostsWithCommentsConnection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE posts_comments (
                    id INTEGER PRIMARY KEY,
                    title VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            '
                CREATE TABLE comments (
                    id INTEGER PRIMARY KEY,
                    post_id INTEGER NOT NULL,
                    body VARCHAR(255) NOT NULL,
                    likes INTEGER NOT NULL DEFAULT 0
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO posts_comments (id, title)
                VALUES
                    (1, 'Hello'),
                    (2, 'Empty')
            ",
        );

        $conn->execute(
            "
                INSERT INTO comments (id, post_id, body, likes)
                VALUES
                    (10, 1, 'a', 3),
                    (11, 1, 'b', 5),
                    (12, 1, 'c', 0)
            ",
        );

        return $conn;
    }

    private function createMorphLogConnection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute('CREATE TABLE morph_logs (id INTEGER PRIMARY KEY, subjectType TEXT, subjectId INTEGER)');
        $conn->execute('CREATE TABLE morph_posts (id INTEGER PRIMARY KEY, title TEXT)');
        $conn->execute('CREATE TABLE morph_videos (id INTEGER PRIMARY KEY, title TEXT)');

        $conn->execute("INSERT INTO morph_posts (id, title) VALUES (1, 'Hello')");
        $conn->execute("INSERT INTO morph_logs (id, subjectType, subjectId) VALUES (1, 'post', 1), (2, 'video', 10), (3, 'unknown', 1)");

        return $conn;
    }
}
