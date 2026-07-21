<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior\Listener;

use DateTimeInterface;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Orm\Behavior\Command\OnCreate;
use PhpSoftBox\Orm\Behavior\Command\OnUpdate;
use PhpSoftBox\Orm\Behavior\ResolvedTimestampColumn;
use PhpSoftBox\Orm\Behavior\TimestampColumnsResolver;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;

use function property_exists;

/**
 * Встроенный listener ORM для created_datetime / updated_datetime.
 */
final readonly class TimestampsListener
{
    private TimestampColumnsResolver $resolver;

    public function __construct(
        private MetadataProviderInterface $metadata,
        ?TimestampColumnsResolver $resolver = null,
    ) {
        $this->resolver = $resolver ?? new TimestampColumnsResolver();
    }

    public function onCreate(OnCreate $event): void
    {
        $meta = $this->metadata->for($event->entity()::class);
        $now  = Clock::now();

        $this->apply($event, $this->resolver->createdForInsert($meta, $now));
        $this->apply($event, $this->resolver->updatedForInsert($meta, $now));
    }

    public function onUpdate(OnUpdate $event): void
    {
        $meta = $this->metadata->for($event->entity()::class);
        $now  = Clock::now();

        $this->apply($event, $this->resolver->updatedForUpdate($meta, $now));
    }

    private function apply(OnCreate|OnUpdate $event, ?ResolvedTimestampColumn $timestamp): void
    {
        if ($timestamp === null) {
            return;
        }

        $event->state()->register($timestamp->column->column, $timestamp->value);
        $this->setEntityProperty($event->entity(), $timestamp->column->property, $timestamp->value);
    }

    private function setEntityProperty(EntityInterface $entity, string $property, DateTimeInterface $value): void
    {
        if (!property_exists($entity, $property)) {
            return;
        }

        $entity->{$property} = $value;
    }
}
