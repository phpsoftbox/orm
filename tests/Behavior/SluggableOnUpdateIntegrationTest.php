<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\IdentityMap\WeakIdentityMap;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\PostWithSlugOnUpdate;
use PhpSoftBox\Orm\UnitOfWork\AdvancedUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class SluggableOnUpdateIntegrationTest extends TestCase
{
    /**
     * Проверяет, что при #[Sluggable(onUpdate: true)] slug пересчитывается при обновлении сущности.
     */
    #[Test]
    public function slugIsRecomputedOnUpdateWhenEnabled(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE posts_update (
                    id INTEGER PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL
                )
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new AdvancedUnitOfWork(new WeakIdentityMap()));

        $post = new PostWithSlugOnUpdate(id: 1, title: 'Hello World', slug: '');

        $em->persist($post);
        $em->flush();

        $post->title = 'Hello New Title';
        $em->persist($post);
        $em->flush();

        $row = $conn->fetchOne('SELECT slug FROM posts_update WHERE id = 1');
        self::assertNotNull($row);
        self::assertSame('hello-new-title', $row['slug']);
    }
}
