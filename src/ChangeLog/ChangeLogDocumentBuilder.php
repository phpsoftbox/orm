<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\ChangeLog;

use DateTimeInterface;
use MongoDB\BSON\UTCDateTime;
use PhpSoftBox\Clock\DatePoint;
use Stringable;

use function array_map;
use function class_exists;
use function get_debug_type;
use function is_array;
use function is_scalar;
use function sprintf;

use const DATE_ATOM;

final class ChangeLogDocumentBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(EntityChangeRecord $record): array
    {
        return [
            'entity_class' => $record->entityClass,
            'entity_id'    => $record->entityId,
            'action'       => $record->action->value,
            'before'       => $this->normalizeValue($record->before),
            'after'        => $this->normalizeValue($record->after),
            'changes'      => $this->normalizeValue($record->changes),
            'initiator'    => [
                'id'       => $this->normalizeValue($record->context->initiatorId),
                'type'     => $record->context->initiatorType,
                'metadata' => $this->normalizeValue($record->context->metadata),
            ],
            'occurred_at'     => $this->toUtcDateTime($record->occurredAt),
            'occurred_at_iso' => DatePoint::from($record->occurredAt)->format(DATE_ATOM),
        ];
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $this->toUtcDateTime($value);
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeValue($item), $value);
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return sprintf('[unsupported:%s]', get_debug_type($value));
    }

    private function toUtcDateTime(DateTimeInterface $value): UTCDateTime|string
    {
        $point = DatePoint::from($value);

        if (class_exists(UTCDateTime::class)) {
            return new UTCDateTime((int) $point->format('Uv'));
        }

        return $point->format(DATE_ATOM);
    }
}
