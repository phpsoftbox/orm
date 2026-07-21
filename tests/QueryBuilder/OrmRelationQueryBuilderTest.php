<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\QueryBuilder;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\QueryBuilder\OrmRelationQueryBuilder;
use PhpSoftBox\Orm\QueryBuilder\OrmSelectQueryBuilder;
use PhpSoftBox\Orm\Tests\Repository\Fixtures\TestEntity;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrmRelationQueryBuilder::class)]
#[CoversMethod(OrmRelationQueryBuilder::class, 'alias')]
#[CoversMethod(OrmRelationQueryBuilder::class, 'pivotAlias')]
#[CoversMethod(OrmRelationQueryBuilder::class, 'qualify')]
#[CoversMethod(OrmRelationQueryBuilder::class, 'where')]
#[CoversMethod(OrmRelationQueryBuilder::class, 'orWhere')]
#[CoversMethod(OrmRelationQueryBuilder::class, 'whereIn')]
#[CoversMethod(OrmRelationQueryBuilder::class, 'whereNotIn')]
final class OrmRelationQueryBuilderTest extends TestCase
{
    /**
     * Проверяет корректную генерацию алиасов и квалификацию колонок.
     */
    #[Test]
    public function testQualifyReturnsExpectedAliases(): void
    {
        $relation = new OrmRelationQueryBuilder($this->makeBuilder(), 'rel', 'pivot');

        $this->assertSame('rel', $relation->alias());
        $this->assertSame('pivot', $relation->pivotAlias());
        $this->assertSame('rel.name', $relation->qualify('name'));
        $this->assertSame('pivot.role_id', $relation->qualify('role_id', true));
        $this->assertSame('t.col', $relation->qualify('t.col'));
    }

    /**
     * Проверяет, что where-методы добавляют условия в родительский SQL.
     */
    #[Test]
    public function testWhereHelpersAppendToParentBuilder(): void
    {
        $builder  = $this->makeBuilder();
        $relation = new OrmRelationQueryBuilder($builder, 'r1', 'p1');

        $relation->where($relation->qualify('name') . ' = :name', ['name' => 'A']);
        $relation->orWhere($relation->qualify('name') . ' = :name2', ['name2' => 'B']);
        $relation->whereIn('id', [1, 2]);
        $relation->whereNotIn('status', [3]);

        $sql = $builder->toSql()['sql'] ?? '';

        $this->assertStringContainsString('"r1"."name" = :name', $sql);
        $this->assertStringContainsString('"r1"."name" = :name2', $sql);
        $this->assertStringContainsString('"r1"."id" IN', $sql);
        $this->assertStringContainsString('"r1"."status" NOT IN', $sql);
    }

    private function makeBuilder(): OrmSelectQueryBuilder
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $em = new EntityManager(connection: $conn, unitOfWork: new UnitOfWork());

        return $em->queryFor(TestEntity::class)->from('test_entities t');
    }
}
