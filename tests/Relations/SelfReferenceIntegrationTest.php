<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Category;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository\CategoryRepository;
use PhpSoftBox\Orm\UnitOfWork\InMemoryUnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class SelfReferenceIntegrationTest extends TestCase
{
    /**
     * Проверяет self-reference связь:
     * - Category::parent (ManyToOne на самого себя)
     * - Category::children (HasMany на самого себя)
     */
    #[Test]
    public function loadsParentAndChildrenForSelfReference(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            "
                CREATE TABLE categories (
                    id INTEGER PRIMARY KEY,
                    parent_id INTEGER NULL
                )
            "
        );

        $conn->execute(
            "
                INSERT INTO categories (id, parent_id) VALUES (1, NULL)
            "
        );
        $conn->execute(
            "
                INSERT INTO categories (id, parent_id) VALUES (2, 1)
            "
        );
        $conn->execute(
            "
                INSERT INTO categories (id, parent_id) VALUES (3, 1)
            "
        );

        $em = new EntityManager(connection: $conn, unitOfWork: new InMemoryUnitOfWork());
        $em->registerRepository(Category::class, new CategoryRepository($conn, $em));

        $child = new Category(id: 2, parentId: 1);
        $em->load($child, ['parent', 'children']);

        self::assertNotNull($child->parent);
        self::assertSame(1, $child->parent->id);

        // children для child (id=2) — пусто
        self::assertCount(0, $child->children->all());

        // Проверяем parent отдельно
        $parent = new Category(id: 1, parentId: null);
        $em->load($parent, 'children');

        self::assertCount(2, $parent->children->all());

        $ids = array_map(static fn (Category $c): int => $c->id, $parent->children->all());
        sort($ids);

        self::assertSame([2, 3], $ids);
    }
}
