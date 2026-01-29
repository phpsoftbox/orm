<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Repository;

use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Metadata\PropertyMetadata;
use PhpSoftBox\Orm\TypeCasting\Contracts\OrmTypeCasterInterface;
use PhpSoftBox\Orm\TypeCasting\Options\TypeCastOptionsManager;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

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
        private OrmTypeCasterInterface $typeCaster,
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

            $options = $this->optionsFromMetadata($colMeta);
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
        $meta = $this->metadata->for($entity::class);

        $data = [];

        foreach ($meta->columns as $property => $colMeta) {
            $value                  = $this->getPublicProperty($entity, $property);
            $options                = $this->optionsFromMetadata($colMeta);
            $data[$colMeta->column] = $this->typeCaster->castTo($colMeta->type, $value, $options);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsFromMetadata(PropertyMetadata $meta): array
    {
        return [
            'type'     => $meta->type,
            'nullable' => $meta->nullable,
            'length'   => $meta->length,
            'default'  => $meta->default,
            ...$this->optionsManager->resolve($meta->type, $meta->options),
        ];
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
