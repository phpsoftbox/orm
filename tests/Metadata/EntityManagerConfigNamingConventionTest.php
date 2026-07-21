<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata;

use PhpSoftBox\Orm\EntityManagerConfig;
use PhpSoftBox\Orm\Metadata\Conventions\InflectorNamingConvention;
use PhpSoftBox\Orm\Metadata\Conventions\NamingConventionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function strtolower;

#[CoversClass(EntityManagerConfig::class)]
final class EntityManagerConfigNamingConventionTest extends TestCase
{
    /**
     * Проверяет, что EntityManagerConfig по умолчанию создаёт namingConvention
     * (через дефолтный EN inflector) и что она генерирует ожидаемые имена.
     */
    #[Test]
    public function createsDefaultNamingConvention(): void
    {
        $config = new EntityManagerConfig(enableBuiltInListeners: false);

        self::assertInstanceOf(NamingConventionInterface::class, $config->namingConvention);
        self::assertSame('users', $config->namingConvention->entityTable('User'));
        self::assertSame('author_id', $config->namingConvention->hasOneManyForeignKeyColumn('author'));
    }

    /**
     * Проверяет, что если namingConvention передана явно, она не перезаписывается дефолтами.
     */
    #[Test]
    public function doesNotOverrideProvidedNamingConvention(): void
    {
        $custom = new class () implements NamingConventionInterface {
            public function entityTable(string $entityClassShortName): string
            {
                return 'x_' . strtolower($entityClassShortName);
            }

            public function belongsToJoinProperty(string $relationProperty): string
            {
                return 'x' . $relationProperty;
            }

            public function hasOneManyForeignKeyColumn(string $relationProperty): string
            {
                return 'x_' . $relationProperty . '_id';
            }

            public function belongsToManyPivotTable(string $leftTable, string $rightTable): string
            {
                return 'x_pivot';
            }

            public function belongsToManyPivotKey(string $table): string
            {
                return 'x_' . $table . '_id';
            }

            public function hasManyThroughFirstKey(string $sourceTable): string
            {
                return 'x_' . $sourceTable . '_id';
            }

            public function hasManyThroughSecondKey(string $targetTable): string
            {
                return 'x_' . $targetTable . '_id';
            }
        };

        $config = new EntityManagerConfig(
            enableBuiltInListeners: false,
            namingConvention: $custom,
        );

        self::assertSame($custom, $config->namingConvention);
        self::assertSame('x_user', $config->namingConvention->entityTable('User'));
    }

    /**
     * Санити-чек: дефолтная namingConvention именно InflectorNamingConvention.
     */
    #[Test]
    public function defaultNamingConventionIsInflectorNamingConvention(): void
    {
        $config = new EntityManagerConfig(enableBuiltInListeners: false);

        self::assertInstanceOf(InflectorNamingConvention::class, $config->namingConvention);
    }
}
