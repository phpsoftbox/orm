<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\EntityManagerConfig;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\CountryHasManyThroughDefaultsEntityManager;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\UserWithBelongsToManyDefaultsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
#[CoversClass(EntityManagerConfig::class)]
final class EntityManagerNamingConventionRelationsIntegrationTest extends TestCase
{
    /**
     * Проверяет, что EntityManagerConfig прокидывает namingConvention в AttributeMetadataProvider,
     * и дефолты BelongsToMany вычисляются.
     */
    #[Test]
    public function belongsToManyDefaultsAreResolvedInsideEntityManager(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $em = new EntityManager(
            connection: $conn,
            config: new EntityManagerConfig(enableBuiltInListeners: false),
        );

        $provider = $em->metadataProvider();

        $meta = $provider->for(UserWithBelongsToManyDefaultsEntityManager::class);
        $rel  = $meta->relations['roles'];

        self::assertSame('belongs_to_many', $rel->type);
        self::assertSame('user_roles', $rel->pivotTable);
        self::assertSame('user_id', $rel->foreignPivotKey);
        self::assertSame('role_id', $rel->relatedPivotKey);
    }

    /**
     * Проверяет, что дефолты HasManyThrough вычисляются.
     */
    #[Test]
    public function hasManyThroughDefaultsAreResolvedInsideEntityManager(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $em = new EntityManager(
            connection: $conn,
            config: new EntityManagerConfig(enableBuiltInListeners: false),
        );

        $provider = $em->metadataProvider();

        $meta = $provider->for(CountryHasManyThroughDefaultsEntityManager::class);
        $rel  = $meta->relations['posts'];

        self::assertSame('has_many_through', $rel->type);
        self::assertSame('country_id', $rel->firstKey);
        self::assertSame('post_id', $rel->secondKey);
    }
}
