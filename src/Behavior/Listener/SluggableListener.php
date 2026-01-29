<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior\Listener;

use PhpSoftBox\Orm\Behavior\Command\OnCreate;
use PhpSoftBox\Orm\Behavior\Command\OnUpdate;
use PhpSoftBox\Orm\Behavior\Slugifier;
use PhpSoftBox\Orm\Metadata\ClassMetadata;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;

use function is_object;
use function is_scalar;
use function is_string;
use function method_exists;
use function preg_replace_callback;

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

            $data = $event->state()->getData();
            $sourceValue = $data[$sluggable->source] ?? null;
            if (!is_string($sourceValue)) {
                continue;
            }

            $slugCore = $this->slugifier->slugify($sourceValue);
            $prefix = $this->renderTemplate($sluggable->prefix, $data);
            $postfix = $this->renderTemplate($sluggable->postfix, $data);

            $event->state()->register($sluggable->target, $prefix . $slugCore . $postfix);
        }
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
                $key = $m[1];
                $value = $data[$key] ?? '';

                if (is_object($value) && method_exists($value, 'toString')) {
                    return (string) $value->toString();
                }

                return is_scalar($value) ? (string) $value : '';
            },
            $template,
        );
    }
}
