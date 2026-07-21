<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Metadata\Conventions;

/**
 * Соглашения (conventions) по именованию таблиц/колонок для ORM.
 *
 * Задача: держать всю "магическую" генерацию имен в одном месте,
 * чтобы её можно было легко переопределять через DI.
 */
interface NamingConventionInterface
{
    /**
     * Возвращает имя таблицы сущности по имени класса (short name).
     *
     * Пример: UserEntity -> users
     */
    public function entityTable(string $entityClassShortName): string;

    /**
     * JOIN property для belongsTo/manyToOne (property-based, не column).
     *
     * Пример: author -> authorId
     */
    public function belongsToJoinProperty(string $relationProperty): string;

    /**
     * Foreign key column в дочерней таблице для hasOne/hasMany.
     *
     * Пример: author -> author_id
     */
    public function hasOneManyForeignKeyColumn(string $relationProperty): string;

    /**
     * Возвращает имя pivot-таблицы для BelongsToMany.
     *
     * Правило по умолчанию: <ownerSingular>_<relatedPlural>.
     *
     * Пример: users + roles -> user_roles
     */
    public function belongsToManyPivotTable(string $leftTable, string $rightTable): string;

    /**
     * Имя foreignPivotKey по имени таблицы.
     *
     * Пример: users -> user_id
     */
    public function belongsToManyPivotKey(string $table): string;

    /**
     * Имя firstKey для hasManyThrough по имени таблицы источника.
     *
     * Пример: countries -> country_id
     */
    public function hasManyThroughFirstKey(string $sourceTable): string;

    /**
     * Имя secondKey для hasManyThrough по имени таблицы target.
     *
     * Пример: posts -> post_id
     */
    public function hasManyThroughSecondKey(string $targetTable): string;
}
