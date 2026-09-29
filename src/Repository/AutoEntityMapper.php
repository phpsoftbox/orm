<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Repository;

use PhpSoftBox\DataCasting\Contracts\TypeCasterInterface;
use PhpSoftBox\DataCasting\JsonHydrationContext;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Exception\UninitializedMappedPropertyException;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Metadata\PropertyMetadata;
use PhpSoftBox\Orm\Support\PropertyAccessor;
use ReflectionClass;
use ReflectionException;

use function array_key_exists;

/**
 * Автоматический mapper сущностей на основе метаданных.
 *
 * Задача: минимальная база для auto-hydrate/extract.
 *
 * Особенности:
 * - читает/пишет свойства через Reflection (любая видимость, включая `readonly`)
 * - создаёт сущность через `newInstanceWithoutConstructor()`
 * - типы берём из #[Column(type: ...)]
 */
final readonly class AutoEntityMapper
{
    public function __construct(
        private MetadataProviderInterface $metadata,
        private TypeCasterInterface $typeCaster,
        private TypeCastOptionsManager $optionsManager,
    ) {
    }

    /**
     * @param class-string $entityClass
     * @param array<string, mixed> $row
     * @throws ReflectionException
     */
    public function hydrate(string $entityClass, array $row): object
    {
        $meta = $this->metadata->for($entityClass);

        $rc = new ReflectionClass($entityClass);

        $entity = $rc->newInstanceWithoutConstructor();

        foreach ($meta->columns as $property => $colMeta) {
            $value = null;

            if (array_key_exists($colMeta->column, $row)) {
                $value = $row[$colMeta->column];
            } elseif (array_key_exists($property, $row)) {
                // fallback: иногда row может приходить уже �� ключами по именам свойств
                $value = $row[$property];
            }

            $jsonContext = $colMeta->type === 'json'
                ? new JsonHydrationContext(
                    source: $row,
                    ownerClass: $entityClass,
                    property: $property,
                    path: '$.' . $property,
                )
                : null;
            $options = $this->optionsFromMetadata($colMeta, $jsonContext);
            $casted  = $this->typeCaster->castFrom($colMeta->type, $value, $options);

            PropertyAccessor::write($entity, $property, $casted);
        }

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $entity): array
    {
        $meta = $this->metadata->for($entity::class);

        $data = [];

        foreach ($meta->columns as $property => $colMeta) {
            if (!PropertyAccessor::isInitialized($entity, $property)) {
                throw UninitializedMappedPropertyException::forProperty($entity::class, $property);
            }

            $data[$colMeta->column] = $this->castToMetadata($colMeta, PropertyAccessor::read($entity, $property));
        }

        return $data;
    }

    public function castFromMetadata(
        PropertyMetadata $meta,
        mixed $value,
        ?JsonHydrationContext $hydrationContext = null,
    ): mixed {
        return $this->typeCaster->castFrom(
            $meta->type,
            $value,
            $this->optionsFromMetadata($meta, $hydrationContext),
        );
    }

    /**
     * Приводит PHP-значение свойства к значению для БД по метаданным колонки.
     */
    public function castToMetadata(PropertyMetadata $meta, mixed $value): mixed
    {
        return $this->typeCaster->castTo($meta->type, $value, $this->optionsFromMetadata($meta));
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsFromMetadata(
        PropertyMetadata $meta,
        ?JsonHydrationContext $hydrationContext = null,
    ): array {
        $options = [
            'type'     => $meta->type,
            'nullable' => $meta->nullable,
            'length'   => $meta->length,
            'default'  => $meta->default,
            ...$this->optionsManager->resolve($meta->type, $meta->options),
        ];

        if ($meta->type !== 'json') {
            return $options;
        }

        if ($meta->jsonCollectionItemClass !== null) {
            $options['collection_item_class'] ??= $meta->jsonCollectionItemClass;
        } elseif ($meta->jsonMapValueClass !== null) {
            $options['map_value_class'] ??= $meta->jsonMapValueClass;
        } elseif ($meta->phpType !== null) {
            $options['target_class'] ??= $meta->phpType;
        }

        if ($meta->jsonFactoryClass !== null) {
            $options['factory_class'] ??= $meta->jsonFactoryClass;
        }

        $options['path'] = '$.' . $meta->property;
        if ($hydrationContext !== null) {
            $options['hydration_context'] = $hydrationContext;
        }

        return $options;
    }
}
