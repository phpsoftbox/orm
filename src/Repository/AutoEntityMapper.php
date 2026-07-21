<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Repository;

use PhpSoftBox\DataCasting\Contracts\TypeCasterInterface;
use PhpSoftBox\DataCasting\JsonHydrationContext;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Exception\UninitializedMappedPropertyException;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Metadata\PropertyMetadata;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;

use function array_key_exists;

/**
 * Автоматический mapper сущностей на основе метаданных.
 *
 * Задача: минимальная база для auto-hydrate/extract.
 *
 * Ограничения текущей версии:
 * - пишет/читает только публичные свойства
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

            $this->setPublicProperty($entity, $property, $casted);
        }

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $entity): array
    {
        $meta       = $this->metadata->for($entity::class);
        $reflection = new ReflectionObject($entity);

        $data = [];

        foreach ($meta->columns as $property => $colMeta) {
            if (!$reflection->getProperty($property)->isInitialized($entity)) {
                throw UninitializedMappedPropertyException::forProperty($entity::class, $property);
            }

            $value                  = $this->getPublicProperty($entity, $property);
            $options                = $this->optionsFromMetadata($colMeta);
            $data[$colMeta->column] = $this->typeCaster->castTo($colMeta->type, $value, $options);
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

    private function setPublicProperty(object $entity, string $property, mixed $value): void
    {
        // Минимальный безопасный вариант: только public свойства.
        // Далее можно расширить на property hooks/private via ReflectionProperty.
        $entity->$property = $value;
    }

    private function getPublicProperty(object $entity, string $property): mixed
    {
        return $entity->$property;
    }
}
