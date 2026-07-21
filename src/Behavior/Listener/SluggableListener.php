<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior\Listener;

use PhpSoftBox\Orm\Behavior\Command\OnCreate;
use PhpSoftBox\Orm\Behavior\Command\OnUpdate;
use PhpSoftBox\Orm\Behavior\Slugifier;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\ClassMetadata;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Metadata\PropertyMetadata;

use function array_key_exists;
use function is_object;
use function is_scalar;
use function is_string;
use function method_exists;
use function preg_replace_callback;
use function property_exists;

/**
 * Встроенный listener ORM для #[Sluggable].
 *
 * Применяется автоматически (не требует объявления #[EventListener] на сущности).
 */
final readonly class SluggableListener
{
    public function __construct(
        private MetadataProviderInterface $metadata,
        private Slugifier $slugifier = new Slugifier(),
    ) {
    }

    public function onCreate(OnCreate $event): void
    {
        $this->apply($this->metadata->for($event->entity()::class), $event);
    }

    public function onUpdate(OnUpdate $event): void
    {
        $this->apply($this->metadata->for($event->entity()::class), $event);
    }

    private function apply(ClassMetadata $meta, OnCreate|OnUpdate $event): void
    {
        foreach ($meta->sluggables as $sluggable) {
            if ($event instanceof OnUpdate && !$sluggable->onUpdate) {
                continue;
            }

            $sourceColumn = $this->resolveColumn($meta, $sluggable->source);
            $targetColumn = $this->resolveColumn($meta, $sluggable->target);
            if ($sourceColumn === null || $targetColumn === null) {
                continue;
            }

            if ($event instanceof OnUpdate && !$targetColumn->updatable) {
                continue;
            }

            if (!($event instanceof OnUpdate) && !$targetColumn->insertable) {
                continue;
            }

            $data        = $event->state()->getData();
            $sourceValue = $data[$sourceColumn->column] ?? null;
            if (!is_string($sourceValue)) {
                continue;
            }

            $templateData = $this->buildTemplateData($meta, $data);
            $slugCore     = $this->slugifier->slugify($sourceValue);
            $prefix       = $this->renderTemplate($sluggable->prefix, $templateData);
            $postfix      = $this->renderTemplate($sluggable->postfix, $templateData);
            $slug         = $prefix . $slugCore . $postfix;

            $event->state()->register($targetColumn->column, $slug);
            $this->setEntityProperty($event->entity(), $targetColumn->property, $slug);
        }
    }

    private function resolveColumn(ClassMetadata $meta, string $propertyOrColumn): ?PropertyMetadata
    {
        if (isset($meta->columns[$propertyOrColumn])) {
            return $meta->columns[$propertyOrColumn];
        }

        foreach ($meta->columns as $column) {
            if ($column->column === $propertyOrColumn) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function buildTemplateData(ClassMetadata $meta, array $data): array
    {
        $templateData = $data;

        foreach ($meta->columns as $column) {
            if (array_key_exists($column->column, $data)) {
                $templateData[$column->property] = $data[$column->column];
            }
        }

        return $templateData;
    }

    /**
     * Подстановка шаблонов вида {field} по данным текущей сущности.
     *
     * Пример: "{id}-" -> "123-"
     *
     * @param array<string, mixed> $data
     */
    private function renderTemplate(string $template, array $data): string
    {
        if ($template === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/\{([a-zA-Z0-9_]+)\}/',
            static function (array $m) use ($data): string {
                $key   = $m[1];
                $value = $data[$key] ?? '';

                if (is_object($value) && method_exists($value, 'toString')) {
                    return (string) $value->toString();
                }

                return is_scalar($value) ? (string) $value : '';
            },
            $template,
        );
    }

    private function setEntityProperty(EntityInterface $entity, string $property, string $value): void
    {
        if (!property_exists($entity, $property)) {
            return;
        }

        $entity->{$property} = $value;
    }
}
