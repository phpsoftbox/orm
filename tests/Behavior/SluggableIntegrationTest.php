<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\IdentityMap\WeakIdentityMap;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\PostWithSlug;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\PostWithSlugPrefixPostfix;
use PhpSoftBox\Orm\UnitOfWork\AdvancedUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class SluggableIntegrationTest extends TestCase
{
    /**
     * Проверяет, что #[Sluggable] генерирует slug из другого поля и записывает его в INSERT.
     */
    #[Test]
    public function sluggableGeneratesSlugOnCreate(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE posts (
                    id INTEGER PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL
                )
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new AdvancedUnitOfWork(new WeakIdentityMap()));

        $post = new PostWithSlug(id: 1, title: 'Hello World', slug: '');

        $em->persist($post);
        $em->flush();

        $row = $conn->fetchOne('SELECT slug FROM posts WHERE id = 1');
        self::assertNotNull($row);
        self::assertSame('hello-world', $row['slug']);
    }

    /**
     * Проверяет, что Sluggable поддерживает prefix/postfix и шаблоны вида {field}.
     */
    #[Test]
    public function sluggableSupportsPrefixPostfixAndTemplates(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE posts2 (
                    id INTEGER PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL
                )
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new AdvancedUnitOfWork(new WeakIdentityMap()));

        $post = new PostWithSlugPrefixPostfix(id: 7, title: 'Hello World', slug: '');

        $em->persist($post);
        $em->flush();

        $row = $conn->fetchOne('SELECT slug FROM posts2 WHERE id = 7');
        self::assertNotNull($row);
        self::assertSame('7-hello-world.html', $row['slug']);
    }
}
