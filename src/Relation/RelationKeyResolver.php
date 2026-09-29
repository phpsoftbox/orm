<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Relation;

use BackedEnum;
use InvalidArgumentException;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Support\PropertyAccessor;
use Throwable;

use function is_bool;
use function is_object;
use function is_scalar;
use function method_exists;

/**
 * Единая семантика ключей связей (`joinColumn`, `referencedColumn`, `localKey`, `foreignKey`,
 * `parentKey`, `relatedKey`, `targetKey`).
 *
 * Ключ — это имя колонки БД. Для удобства допускается имя свойства сущности: ORM переводит его в колонку
 * через метаданные `#[Column]`. Колонка используется в SQL, соответствующее ей свойство — для чтения
 * значения из сущности.
 */
final readonly class RelationKeyResolver
{
    public function __construct(
        private MetadataProviderInterface $metadata,
    ) {
    }

    /**
     * @param class-string|string $entityClass
     */
    public function resolve(string $entityClass, string $key): RelationKey
    {
        try {
            $meta = $this->metadata->for($entityClass);
        } catch (Throwable) {
            return new RelationKey(column: $key, property: $key);
        }

        // 1) ключ задан именем свойства
        if (isset($meta->columns[$key])) {
            return new RelationKey(column: $meta->columns[$key]->column, property: $key);
        }

        // 2) ключ задан именем колонки
        foreach ($meta->columns as $property => $column) {
            if ($column->column === $key) {
                return new RelationKey(column: $key, property: $property);
            }
        }

        // 3) немаппированный ключ: колонка и свойство совпадают по имени
        return new RelationKey(column: $key, property: $key);
    }

    public function column(string $entityClass, string $key): string
    {
        return $this->resolve($entityClass, $key)->column;
    }

    /**
     * Читает значение ключа из сущности и нормализует его для сравнения/SQL (UUID и backed enum → scalar).
     */
    public function readValue(object $entity, string $key): int|string|float|null
    {
        $property = $this->resolve($entity::class, $key)->property;

        if (!PropertyAccessor::has($entity, $property)) {
            throw new InvalidArgumentException(
                'Relation key "' . $key . '" does not match any property of ' . $entity::class . '.',
            );
        }

        return self::normalize(PropertyAccessor::read($entity, $property));
    }

    /**
     * Нормализует значение ключа: UUID/объекты с toString() → string, backed enum → value.
     */
    public static function normalize(mixed $value): int|string|float|null
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_object($value) && method_exists($value, 'toString')) {
            $value = $value->toString();
        }

        if ($value === null || is_bool($value) || !is_scalar($value)) {
            return null;
        }

        return $value;
    }
}
