<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\MorphComment;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Post;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithMorphComments;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\MorphCommentRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\PostRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\PostWithMorphCommentsRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\VideoRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Video;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class MorphRelationsIntegrationTest extends TestCase
{
    /**
     * Проверяет MorphTo: MorphComment::commentable загружается в Post/Video в зависимости от commentable_type.
     */
    #[Test]
    public function loadsMorphToTargets(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

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
                CREATE TABLE videos (
                    id INTEGER PRIMARY KEY,
                    title VARCHAR(255) NOT NULL
                )
            "
        );

        $conn->execute(
            "
                CREATE TABLE morph_comments (
                    id INTEGER PRIMARY KEY,
                    commentable_type VARCHAR(16) NOT NULL,
                    commentable_id INTEGER NOT NULL
                )
            "
        );

        $conn->execute(
            "
                INSERT INTO posts_rel (id, author_id) VALUES (10, 1)
            "
        );

        $conn->execute(
            "
                INSERT INTO videos (id, title) VALUES (77, 'Video Title')
            "
        );

        $conn->execute(
            "
                INSERT INTO morph_comments (id, commentable_type, commentable_id) VALUES (1, 'post', 10)
            "
        );
        $conn->execute(
            "
                INSERT INTO morph_comments (id, commentable_type, commentable_id) VALUES (2, 'video', 77)
            "
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());
        $em->registerRepository(MorphComment::class, new MorphCommentRepository($conn, $em));
        $em->registerRepository(Post::class, new PostRepository($conn, $em));
        $em->registerRepository(Video::class, new VideoRepository($conn, $em));

        $c1 = new MorphComment(id: 1, commentableType: 'post', commentableId: 10);
        $c2 = new MorphComment(id: 2, commentableType: 'video', commentableId: 77);

        $em->load([$c1, $c2], 'commentable');

        self::assertInstanceOf(Post::class, $c1->commentable);
        self::assertSame(10, $c1->commentable->id);

        self::assertInstanceOf(Video::class, $c2->commentable);
        self::assertSame(77, $c2->commentable->id);
    }

    /**
     * Проверяет MorphMany: PostWithMorphComments::comments загружается по (typeValue + id).
     */
    #[Test]
    public function loadsMorphManyCollection(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            "
                CREATE TABLE posts_morph (
                    id INTEGER PRIMARY KEY,
                    title VARCHAR(255) NOT NULL
                )
            "
        );

        $conn->execute(
            "
                CREATE TABLE morph_comments (
                    id INTEGER PRIMARY KEY,
                    commentable_type VARCHAR(16) NOT NULL,
                    commentable_id INTEGER NOT NULL
                )
            "
        );

        $conn->execute(
            "
                INSERT INTO posts_morph (id, title) VALUES (1, 'Post 1')
            "
        );
        $conn->execute(
            "
                INSERT INTO morph_comments (id, commentable_type, commentable_id) VALUES (10, 'post', 1)
            "
        );
        $conn->execute(
            "
                INSERT INTO morph_comments (id, commentable_type, commentable_id) VALUES (11, 'post', 1)
            "
        );
        $conn->execute(
            "
                INSERT INTO morph_comments (id, commentable_type, commentable_id) VALUES (12, 'video', 1)
            "
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());
        $em->registerRepository(PostWithMorphComments::class, new PostWithMorphCommentsRepository($conn, $em));
        $em->registerRepository(MorphComment::class, new MorphCommentRepository($conn, $em));

        $post = new PostWithMorphComments(id: 1, title: 'Post 1');
        $em->load($post, 'comments');

        // Должны загрузиться только commentable_type = 'post'
        self::assertCount(2, $post->comments->all());
        $ids = array_map(static fn (MorphComment $c): int => $c->id, $post->comments->all());
        sort($ids);
        self::assertSame([10, 11], $ids);
    }
}
