<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\AuthorAwardNested;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\AuthorBookNested;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\AuthorNested;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\CommentWithAuthor;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithNestedComments;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\AuthorAwardNestedRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\AuthorBookNestedRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\AuthorNestedRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\CommentWithAuthorRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\PostWithNestedCommentsRepository;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class NestedHasManyIntegrationTest extends TestCase
{
    /**
     * Проверяет nested eager loading через hasMany: with(['comments.author']).
     */
    #[Test]
    public function withSupportsNestedThroughHasManyCollections(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE posts_nested_comments (
                    id INTEGER PRIMARY KEY,
                    title VARCHAR(255) NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE authors_nested (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE comments_nested (
                    id INTEGER PRIMARY KEY,
                    post_id INTEGER NOT NULL,
                    author_id INTEGER NOT NULL,
                    body VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO posts_nested_comments (id, title) VALUES (1, 'Hello')
            ",
        );
        $conn->execute(
            "
                INSERT INTO authors_nested (id, name) VALUES (10, 'Anton')
            ",
        );
        $conn->execute(
            "
                INSERT INTO comments_nested (id, post_id, author_id, body) VALUES (100, 1, 10, 'a')
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $em->registerRepository(PostWithNestedComments::class, new PostWithNestedCommentsRepository($conn, $em));
        $em->registerRepository(CommentWithAuthor::class, new CommentWithAuthorRepository($conn, $em));
        $em->registerRepository(AuthorNested::class, new AuthorNestedRepository($conn, $em));

        $repo = $em->repository(PostWithNestedComments::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        /** @var AbstractEntityRepository<PostWithNestedComments> $repo */
        $posts = $repo->with(['comments.author'])->all();

        $items = $posts->all();
        self::assertCount(1, $items);

        self::assertNotNull($items[0]->comments);
        self::assertCount(1, $items[0]->comments->all());

        $comment = $items[0]->comments->all()[0];
        self::assertSame('a', $comment->body);
        self::assertNotNull($comment->author);
        self::assertSame(10, $comment->author->id);
        self::assertSame('Anton', $comment->author->name);
    }

    /**
     * Проверяет, что nested eager loading не теряет детей после цепочки hasMany -> belongsTo -> hasMany.
     */
    #[Test]
    public function withPreservesNestedChildrenAfterHasManyAndBelongsTo(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE posts_nested_comments (
                    id INTEGER PRIMARY KEY,
                    title VARCHAR(255) NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE authors_nested (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE comments_nested (
                    id INTEGER PRIMARY KEY,
                    post_id INTEGER NOT NULL,
                    author_id INTEGER NOT NULL,
                    body VARCHAR(255) NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE author_books_nested (
                    id INTEGER PRIMARY KEY,
                    author_id INTEGER NOT NULL,
                    title VARCHAR(255) NOT NULL
                )
            ',
        );
        $conn->execute(
            '
                CREATE TABLE author_awards_nested (
                    id INTEGER PRIMARY KEY,
                    author_id INTEGER NOT NULL,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO posts_nested_comments (id, title) VALUES (1, 'Hello')
            ",
        );
        $conn->execute(
            "
                INSERT INTO authors_nested (id, name) VALUES (10, 'Anton')
            ",
        );
        $conn->execute(
            "
                INSERT INTO comments_nested (id, post_id, author_id, body) VALUES (100, 1, 10, 'a')
            ",
        );
        $conn->execute(
            "
                INSERT INTO author_books_nested (id, author_id, title) VALUES (1000, 10, 'Clean Architecture')
            ",
        );
        $conn->execute(
            "
                INSERT INTO author_awards_nested (id, author_id, name) VALUES (2000, 10, 'Booker Prize')
            ",
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        $em->registerRepository(PostWithNestedComments::class, new PostWithNestedCommentsRepository($conn, $em));
        $em->registerRepository(CommentWithAuthor::class, new CommentWithAuthorRepository($conn, $em));
        $em->registerRepository(AuthorNested::class, new AuthorNestedRepository($conn, $em));
        $em->registerRepository(AuthorBookNested::class, new AuthorBookNestedRepository($conn, $em));
        $em->registerRepository(AuthorAwardNested::class, new AuthorAwardNestedRepository($conn, $em));

        $repo = $em->repository(PostWithNestedComments::class);
        self::assertInstanceOf(AbstractEntityRepository::class, $repo);

        /** @var AbstractEntityRepository<PostWithNestedComments> $repo */
        $posts = $repo->with(['comments.author.books', 'comments.author.awards'])->all();

        $post = $posts->all()[0] ?? null;
        self::assertNotNull($post);
        self::assertNotNull($post->comments);

        $comment = $post->comments->all()[0] ?? null;
        self::assertNotNull($comment);
        self::assertNotNull($comment->author);
        self::assertNotNull($comment->author->books);
        self::assertNotNull($comment->author->awards);

        $books  = $comment->author->books->all();
        $awards = $comment->author->awards->all();

        self::assertCount(1, $books);
        self::assertCount(1, $awards);
        self::assertSame('Clean Architecture', $books[0]->title);
        self::assertSame('Booker Prize', $awards[0]->name);
    }
}
