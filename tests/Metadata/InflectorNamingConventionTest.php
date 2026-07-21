<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Metadata;

use PhpSoftBox\Inflector\InflectorFactory;
use PhpSoftBox\Inflector\LanguageEnum;
use PhpSoftBox\Orm\Metadata\Conventions\InflectorNamingConvention;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InflectorNamingConvention::class)]
final class InflectorNamingConventionTest extends TestCase
{
    /**
     * Проверяет правило преобразования имени класса (short name) в имя таблицы.
     */
    #[Test]
    public function entityTableIsGuessedFromShortClassName(): void
    {
        $convention = new InflectorNamingConvention(
            InflectorFactory::create(LanguageEnum::EN),
        );

        self::assertSame('users', $convention->entityTable('User'));
        self::assertSame('blog_posts', $convention->entityTable('BlogPost'));
    }

    /**
     * Проверяет правило join property для belongsTo/manyToOne.
     */
    #[Test]
    public function belongsToJoinPropertyAddsIdSuffix(): void
    {
        $convention = new InflectorNamingConvention(
            InflectorFactory::create(LanguageEnum::EN),
        );

        self::assertSame('authorId', $convention->belongsToJoinProperty('author'));
        self::assertSame('userId', $convention->belongsToJoinProperty('user'));
    }

    /**
     * Проверяет правило foreign key column для hasOne/hasMany.
     */
    #[Test]
    public function hasOneManyForeignKeyColumnUsesSnakeCaseAndIdSuffix(): void
    {
        $convention = new InflectorNamingConvention(
            InflectorFactory::create(LanguageEnum::EN),
        );

        self::assertSame('author_id', $convention->hasOneManyForeignKeyColumn('author'));
        self::assertSame('blog_post_id', $convention->hasOneManyForeignKeyColumn('blogPost'));
    }

    /**
     * Проверяет правило имени pivot-таблицы для BelongsToMany.
     *
     * По умолчанию: <ownerSingular>_<relatedPlural>.
     */
    #[Test]
    public function belongsToManyPivotTableUsesOwnerSingularAndRelatedPlural(): void
    {
        $convention = new InflectorNamingConvention(
            InflectorFactory::create(LanguageEnum::EN),
        );

        self::assertSame('user_roles', $convention->belongsToManyPivotTable('users', 'roles'));
        self::assertSame('role_users', $convention->belongsToManyPivotTable('roles', 'users'));
    }

    /**
     * Проверяет правило pivot-key: singular(table) + _id.
     */
    #[Test]
    public function belongsToManyPivotKeyUsesSingularAndIdSuffix(): void
    {
        $convention = new InflectorNamingConvention(
            InflectorFactory::create(LanguageEnum::EN),
        );

        self::assertSame('user_id', $convention->belongsToManyPivotKey('users'));
        self::assertSame('role_id', $convention->belongsToManyPivotKey('roles'));
    }

    /**
     * Проверяет правило ключей для hasManyThrough.
     */
    #[Test]
    public function hasManyThroughKeysUseSingularAndIdSuffix(): void
    {
        $convention = new InflectorNamingConvention(
            InflectorFactory::create(LanguageEnum::EN),
        );

        self::assertSame('country_id', $convention->hasManyThroughFirstKey('countries'));
        self::assertSame('post_id', $convention->hasManyThroughSecondKey('posts'));
    }
}
