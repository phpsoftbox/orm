<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\PostgresDriver;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\QueryBuilder\OrmRelationQueryBuilder;
use PhpSoftBox\Orm\QueryBuilder\OrmSelectQueryBuilder;
use PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures\ManyToOnePost;
use PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures\MorphLog;
use PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures\MorphPost;
use PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures\RelUser;
use PhpSoftBox\Orm\Tests\QueryBuilder\Fixtures\ThroughCompany;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithNestedComments;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithVisibleComments;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrmSelectQueryBuilder::class)]
#[CoversMethod(OrmSelectQueryBuilder::class, 'whereHas')]
final class OrmSelectQueryBuilderWhereHasTest extends TestCase
{
    /**
     * Проверяет фильтрацию belongsToMany через whereHas().
     */
    #[Test]
    public function testWhereHasBelongsToMany(): void
    {
        $em   = $this->makeEntityManager();
        $conn = $em->connection();

        $conn->execute('CREATE TABLE rel_users (id INTEGER PRIMARY KEY, name TEXT)');
        $conn->execute('CREATE TABLE rel_roles (id INTEGER PRIMARY KEY, name TEXT)');
        $conn->execute('CREATE TABLE rel_user_roles (id INTEGER PRIMARY KEY, user_id INTEGER, role_id INTEGER)');

        $conn->execute("INSERT INTO rel_users (id, name) VALUES (1, 'A'), (2, 'B')");
        $conn->execute("INSERT INTO rel_roles (id, name) VALUES (1, 'Admin'), (2, 'Editor')");
        $conn->execute('INSERT INTO rel_user_roles (id, user_id, role_id) VALUES (1, 1, 1)');

        $users = $em->queryFor(RelUser::class)
            ->from('rel_users u')
            ->whereHas('roles', static function (OrmRelationQueryBuilder $q): void {
                $q->where($q->qualify('role_id', true) . ' = :role_id', ['role_id' => 1]);
            })
            ->orderBy('u.id')
            ->fetchEntities();

        $this->assertCount(1, $users->all());
        $this->assertSame(1, $users->first()?->id);
    }

    /**
     * Проверяет whereHas() для many_to_one, когда joinColumn задан как property (authorId),
     * а физическая колонка в БД — author_id.
     */
    #[Test]
    public function testWhereHasManyToOneResolvesJoinPropertyToColumn(): void
    {
        $em   = $this->makeEntityManager();
        $conn = $em->connection();

        $conn->execute('CREATE TABLE qb_authors (id INTEGER PRIMARY KEY, name TEXT)');
        $conn->execute('CREATE TABLE qb_posts (id INTEGER PRIMARY KEY, author_id INTEGER, title TEXT)');

        $conn->execute("INSERT INTO qb_authors (id, name) VALUES (1, 'Anton'), (2, 'Alex')");
        $conn->execute("INSERT INTO qb_posts (id, author_id, title) VALUES (11, 1, 'One'), (12, 2, 'Two')");

        $posts = $em->queryFor(ManyToOnePost::class)
            ->from('qb_posts p')
            ->whereHas('author', static function (OrmRelationQueryBuilder $q): void {
                $q->where($q->qualify('name') . ' = :name', ['name' => 'Anton']);
            })
            ->orderBy('p.id')
            ->fetchEntities();

        $this->assertCount(1, $posts->all());
        $this->assertSame(11, $posts->first()?->id);
    }

    /**
     * Проверяет фильтрацию hasManyThrough через whereHas().
     */
    #[Test]
    public function testWhereHasManyThrough(): void
    {
        $em   = $this->makeEntityManager();
        $conn = $em->connection();

        $conn->execute('CREATE TABLE through_companies (id INTEGER PRIMARY KEY, name TEXT)');
        $conn->execute('CREATE TABLE through_work_items (id INTEGER PRIMARY KEY, title TEXT)');
        $conn->execute('CREATE TABLE through_company_work_items (id INTEGER PRIMARY KEY, company_id INTEGER, work_item_id INTEGER)');

        $conn->execute("INSERT INTO through_companies (id, name) VALUES (1, 'One'), (2, 'Two')");
        $conn->execute("INSERT INTO through_work_items (id, title) VALUES (10, 'Alpha'), (11, 'Beta')");
        $conn->execute('INSERT INTO through_company_work_items (id, company_id, work_item_id) VALUES (1, 1, 10), (2, 2, 11)');

        $companies = $em->queryFor(ThroughCompany::class)
            ->from('through_companies c')
            ->whereHas('workItems', static function (OrmRelationQueryBuilder $q): void {
                $q->where($q->qualify('title') . ' = :title', ['title' => 'Alpha']);
            })
            ->orderBy('c.id')
            ->fetchEntities();

        $this->assertCount(1, $companies->all());
        $this->assertSame(1, $companies->first()?->id);
    }

    /**
     * Проверяет фильтрацию morphMany через whereHas().
     */
    #[Test]
    public function testWhereHasMorphMany(): void
    {
        $em   = $this->makeEntityManager();
        $conn = $em->connection();

        $conn->execute('CREATE TABLE morph_posts (id INTEGER PRIMARY KEY, title TEXT)');
        $conn->execute('CREATE TABLE morph_comments (id INTEGER PRIMARY KEY, commentable_type TEXT, commentable_id INTEGER, body TEXT)');

        $conn->execute("INSERT INTO morph_posts (id, title) VALUES (1, 'First'), (2, 'Second')");
        $conn->execute("INSERT INTO morph_comments (id, commentable_type, commentable_id, body) VALUES (1, 'post', 1, 'hello'), (2, 'post', 2, 'bye')");

        $posts = $em->queryFor(MorphPost::class)
            ->from('morph_posts p')
            ->whereHas('comments', static function (OrmRelationQueryBuilder $q): void {
                $q->where($q->qualify('body') . ' LIKE :q', ['q' => '%hello%']);
            })
            ->orderBy('p.id')
            ->fetchEntities();

        $this->assertCount(1, $posts->all());
        $this->assertSame(1, $posts->first()?->id);
    }

    /**
     * Проверяет фильтрацию morphTo через whereHas().
     */
    #[Test]
    public function testWhereHasMorphTo(): void
    {
        $em   = $this->makeEntityManager();
        $conn = $em->connection();

        $conn->execute('CREATE TABLE morph_logs (id INTEGER PRIMARY KEY, subjectType TEXT, subjectId INTEGER)');
        $conn->execute('CREATE TABLE morph_posts (id INTEGER PRIMARY KEY, title TEXT)');
        $conn->execute('CREATE TABLE morph_videos (id INTEGER PRIMARY KEY, title TEXT)');

        $conn->execute("INSERT INTO morph_posts (id, title) VALUES (1, 'Hello'), (2, 'World')");
        $conn->execute("INSERT INTO morph_videos (id, title) VALUES (10, 'Intro')");
        $conn->execute("INSERT INTO morph_logs (id, subjectType, subjectId) VALUES (1, 'post', 1), (2, 'video', 10)");

        $logs = $em->queryFor(MorphLog::class)
            ->from('morph_logs l')
            ->whereHas('subject', static function (OrmRelationQueryBuilder $q): void {
                $q->where($q->qualify('title') . ' = :title', ['title' => 'Hello']);
            })
            ->orderBy('l.id')
            ->fetchEntities();

        $this->assertCount(1, $logs->all());
        $this->assertSame(1, $logs->first()?->id);
    }

    /**
     * Проверяет, что whereHas() учитывает RelationScope, объявленный на связи.
     */
    #[Test]
    public function testWhereHasUsesRelationScope(): void
    {
        $em   = $this->makeEntityManager();
        $conn = $em->connection();

        $conn->execute('CREATE TABLE posts_comments (id INTEGER PRIMARY KEY, title TEXT)');
        $conn->execute('CREATE TABLE comments (id INTEGER PRIMARY KEY, post_id INTEGER, body TEXT)');

        $conn->execute("INSERT INTO posts_comments (id, title) VALUES (1, 'Hidden only'), (2, 'Visible')");
        $conn->execute("INSERT INTO comments (id, post_id, body) VALUES (10, 1, 'b'), (11, 2, 'c')");

        $posts = $em->queryFor(PostWithVisibleComments::class)
            ->from('posts_comments p')
            ->whereHas('visibleComments')
            ->orderBy('p.id')
            ->fetchEntities();

        $this->assertCount(1, $posts->all());
        $this->assertSame(2, $posts->first()?->id);
    }

    /**
     * Проверяет, что whereHas() не генерирует camelCase alias.
     * В PostgreSQL unquoted alias приводится к нижнему регистру, а quoted alias сохраняет регистр.
     */
    #[Test]
    public function testWhereHasUsesLowerSnakeCaseAliasesForCamelCaseRelations(): void
    {
        $conn = new Connection(new PDO('sqlite::memory:'), new PostgresDriver());

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $built = $em->queryFor(PostWithVisibleComments::class)
            ->whereHas('visibleComments')
            ->query()
            ->toSql();

        self::assertStringContainsString('rel_visible_comments_1', $built['sql']);
        self::assertStringNotContainsString('visibleComments', $built['sql']);
    }

    #[Test]
    public function testWhereHasSupportsNestedRelationPathsWithoutDuplicatingRoots(): void
    {
        $em   = $this->makeEntityManager();
        $conn = $em->connection();

        $conn->execute('CREATE TABLE posts_nested_comments (id INTEGER PRIMARY KEY, title TEXT)');
        $conn->execute('CREATE TABLE comments_nested (id INTEGER PRIMARY KEY, post_id INTEGER, author_id INTEGER, body TEXT)');
        $conn->execute('CREATE TABLE authors_nested (id INTEGER PRIMARY KEY, name TEXT)');
        $conn->execute('CREATE TABLE author_books_nested (id INTEGER PRIMARY KEY, author_id INTEGER, title TEXT)');

        $conn->execute("INSERT INTO posts_nested_comments (id, title) VALUES (1, 'One'), (2, 'Two')");
        $conn->execute("INSERT INTO authors_nested (id, name) VALUES (10, 'A'), (20, 'B')");
        $conn->execute("INSERT INTO comments_nested (id, post_id, author_id, body) VALUES (100, 1, 10, 'a'), (101, 1, 10, 'b'), (102, 2, 20, 'c')");
        $conn->execute("INSERT INTO author_books_nested (id, author_id, title) VALUES (1000, 10, 'Target')");

        $posts = $em->queryFor(PostWithNestedComments::class)
            ->whereHas(
                'comments.author.books',
                static fn (OrmRelationQueryBuilder $query) => $query->whereProperty('title', '=', 'Target'),
            )
            ->fetchEntities();

        self::assertCount(1, $posts->all());
        self::assertSame(1, $posts->first()?->id);

        $withoutBooks = $em->queryFor(PostWithNestedComments::class)
            ->whereDoesntHave('comments.author.books')
            ->fetchEntities();

        self::assertCount(1, $withoutBooks->all());
        self::assertSame(2, $withoutBooks->first()?->id);
    }

    private function makeEntityManager(): EntityManager
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        return new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());
    }
}
