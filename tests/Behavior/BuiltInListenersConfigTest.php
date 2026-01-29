<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\EntityManagerConfig;
use PhpSoftBox\Orm\IdentityMap\WeakIdentityMap;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\PostWithSlug;
use PhpSoftBox\Orm\UnitOfWork\AdvancedUnitOfWork;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
#[CoversClass(EntityManagerConfig::class)]
final class BuiltInListenersConfigTest extends TestCase
{
    /**
     * Проверяет, что при enableBuiltInListeners=false встроенные behaviors не применяются
     * (slug остаётся таким, каким его передали при создании сущности).
     */
    #[Test]
    public function disablingBuiltInListenersDisablesSluggable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());
        $conn->execute(
            "
                CREATE TABLE posts (
                    id INTEGER PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL
                )
            "
        );

        $em = new EntityManager(
            connection: $conn,
            unitOfWork: new AdvancedUnitOfWork(new WeakIdentityMap()),
            config: new EntityManagerConfig(enableBuiltInListeners: false),
        );

        $post = new PostWithSlug(id: 1, title: 'Hello World', slug: 'custom');
        $em->persist($post);
        $em->flush();

        $row = $conn->fetchOne('SELECT slug FROM posts WHERE id = 1');
        self::assertNotNull($row);
        self::assertSame('custom', $row['slug']);
    }
}
