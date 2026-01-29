<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Behavior;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\IdentityMap\WeakIdentityMap;
use PhpSoftBox\Orm\Tests\Behavior\Fixtures\PostWithSlugMissingTemplateVar;
use PhpSoftBox\Orm\UnitOfWork\AdvancedUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class SluggableMissingTemplateVarIntegrationTest extends TestCase
{
    /**
     * Проверяет, что неизвестные переменные в prefix/postfix (например {missing}) заменяются на пустую строку.
     */
    #[Test]
    public function unknownTemplateVarIsReplacedWithEmptyString(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE posts_missing (
                    id INTEGER PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL
                )
            ',
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new AdvancedUnitOfWork(new WeakIdentityMap()));

        $post = new PostWithSlugMissingTemplateVar(id: 10, title: 'Hello World', slug: '');

        $em->persist($post);
        $em->flush();

        $row = $conn->fetchOne('SELECT slug FROM posts_missing WHERE id = 10');
        self::assertNotNull($row);
        self::assertSame('10--hello-world', $row['slug']);
    }
}
