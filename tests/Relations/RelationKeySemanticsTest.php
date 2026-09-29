<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\QueryBuilder\OrmSelectQueryBuilder;
use PhpSoftBox\Orm\Relation\PivotRelationWriter;
use PhpSoftBox\Orm\Relation\RelationKeyResolver;
use PhpSoftBox\Orm\Tests\Relations\FixturesKeys\KeyAuthor;
use PhpSoftBox\Orm\Tests\Relations\FixturesKeys\KeyBook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(EntityManager::class)]
#[CoversClass(RelationKeyResolver::class)]
#[CoversClass(PivotRelationWriter::class)]
#[CoversClass(OrmSelectQueryBuilder::class)]
#[CoversMethod(EntityManager::class, 'load')]
#[CoversMethod(RelationKeyResolver::class, 'resolve')]
#[CoversMethod(PivotRelationWriter::class, 'attach')]
#[CoversMethod(OrmSelectQueryBuilder::class, 'with')]
final class RelationKeySemanticsTest extends TestCase
{
    /**
     * Проверим, что BelongsTo с ключами-колонками (`author_code` → `external_code`) загружается:
     * колонка используется в SQL, соответствующее свойство — для чтения значения.
     *
     * @see EntityManager::load()
     * @see RelationKeyResolver::resolve()
     */
    #[Test]
    public function belongsToLoadsWithColumnKeys(): void
    {
        $em = new EntityManager(connection: $this->connection());

        $book = $em->find(KeyBook::class, 100);
        $em->load($book, 'author');

        self::assertInstanceOf(KeyAuthor::class, $book->author);
        self::assertSame(1, $book->author->id);
    }

    /**
     * Проверим, что BelongsTo с ключами-свойствами (`authorCode` → `externalCode`) загружается так же,
     * как с ключами-колонками.
     *
     * @see EntityManager::load()
     * @see RelationKeyResolver::resolve()
     */
    #[Test]
    public function belongsToLoadsWithPropertyKeys(): void
    {
        $em = new EntityManager(connection: $this->connection());

        $book = $em->find(KeyBook::class, 100);
        $em->load($book, 'authorByProperty');

        self::assertInstanceOf(KeyAuthor::class, $book->authorByProperty);
        self::assertSame(1, $book->authorByProperty->id);
    }

    /**
     * Проверим, что HasMany с localKey-колонкой (`external_code`) находит дочерние сущности.
     *
     * @see EntityManager::load()
     */
    #[Test]
    public function hasManyLoadsByLocalKeyColumn(): void
    {
        $em = new EntityManager(connection: $this->connection());

        $author = $em->find(KeyAuthor::class, 1);
        $em->load($author, 'books');

        self::assertSame([100, 101], array_map(static fn (KeyBook $book): int => $book->id, $author->books->all()));
    }

    /**
     * Проверим, что pivot attach пишет в pivot-таблицу значение parentKey owner'а, а не его id,
     * и связь затем загружается по тому же ключу.
     *
     * @see PivotRelationWriter::attach()
     * @see EntityManager::load()
     */
    #[Test]
    public function pivotAttachUsesParentKey(): void
    {
        $conn = $this->connection();
        $em   = new EntityManager(connection: $conn);

        $author = $em->find(KeyAuthor::class, 1);
        $em->pivot($author, 'tags')->attach(7);

        self::assertSame('A-1', $conn->fetchOne('SELECT author_code FROM key_author_tags')['author_code']);

        $em->load($author, 'tags');
        self::assertSame(7, $author->tags->first()?->id);
    }

    /**
     * Проверим, что повторные вызовы with() накапливают связи, а не заменяют предыдущие.
     *
     * @see OrmSelectQueryBuilder::with()
     */
    #[Test]
    public function withCallsAccumulateRelations(): void
    {
        $em = new EntityManager(connection: $this->connection());

        $book = $em->queryFor(KeyBook::class)
            ->where('id = :id', ['id' => 100])
            ->with('author')
            ->with('authorByProperty')
            ->fetchEntity();

        self::assertInstanceOf(KeyBook::class, $book);
        self::assertTrue($em->unitOfWork()->isRelationLoaded($book, 'author'));
        self::assertTrue($em->unitOfWork()->isRelationLoaded($book, 'authorByProperty'));
    }

    private function connection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute('CREATE TABLE key_authors (id INTEGER PRIMARY KEY, external_code VARCHAR(32) NOT NULL)');
        $conn->execute('CREATE TABLE key_books (id INTEGER PRIMARY KEY, author_code VARCHAR(32) NOT NULL)');
        $conn->execute('CREATE TABLE key_tags (id INTEGER PRIMARY KEY, name VARCHAR(32) NOT NULL)');
        $conn->execute('CREATE TABLE key_author_tags (author_code VARCHAR(32) NOT NULL, tag_id INTEGER NOT NULL)');

        $conn->execute('INSERT INTO key_authors (id, external_code) VALUES (1, \'A-1\'), (2, \'A-2\')');
        $conn->execute('INSERT INTO key_books (id, author_code) VALUES (100, \'A-1\'), (101, \'A-1\'), (102, \'A-2\')');
        $conn->execute('INSERT INTO key_tags (id, name) VALUES (7, \'php\')');

        return $conn;
    }
}
