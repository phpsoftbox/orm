<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\Behavior\Attributes\Listen;
use PhpSoftBox\Orm\Behavior\Command\OnCreate;
use PhpSoftBox\Orm\Behavior\Slugifier;
use PhpSoftBox\Orm\Contracts\BuiltInListenersRegistryInterface;
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
final class CustomBuiltInListenersRegistryTest extends TestCase
{
    /**
     * Проверяет, что можно подменить built-in listeners через кастомный registry.
     *
     * В тесте мы регистрируем listener, который ставит slug в верхнем регистре.
     */
    #[Test]
    public function customBuiltInListenersRegistryIsUsed(): void
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

        $registry = new class () implements BuiltInListenersRegistryInterface {
            public function listeners(): array
            {
                return [
                    new class () {
                        #[Listen(OnCreate::class)]
                        public function onCreate(OnCreate $event): void
                        {
                            $data = $event->state()->getData();
                            $title = (string) ($data['title'] ?? '');
                            $slug = strtoupper((new Slugifier())->slugify($title));
                            $event->state()->register('slug', $slug);
                        }
                    },
                ];
            }
        };

        $em = new EntityManager(
            connection: $conn,
            unitOfWork: new AdvancedUnitOfWork(new WeakIdentityMap()),
            config: new EntityManagerConfig(
                enableBuiltInListeners: true,
                builtInListenersRegistry: $registry,
            ),
        );

        $post = new PostWithSlug(id: 1, title: 'Hello World', slug: '');
        $em->persist($post);
        $em->flush();

        $row = $conn->fetchOne('SELECT slug FROM posts WHERE id = 1');
        self::assertNotNull($row);
        self::assertSame('HELLO-WORLD', $row['slug']);
    }
}

