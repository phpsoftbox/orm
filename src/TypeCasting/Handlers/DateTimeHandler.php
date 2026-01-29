<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PhpSoftBox\Orm\TypeCasting\Contracts\OrmTypeHandlerInterface;
use Throwable;

final readonly class DateTimeHandler implements OrmTypeHandlerInterface
{
    /**
     * @param class-string<DateTimeInterface> $dateTimeClass
     */
    public function __construct(
        private string $dateTimeClass = DateTimeImmutable::class,
        private string $format = DateTimeInterface::ATOM,
    ) {
    }

    public function supports(string $type): bool
    {
        return $type === 'datetime' || $type === 'date' || $type === 'time';
    }

    public function castTo(mixed $value, array $options = []): int|float|string|bool|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeInterface) {
            throw new InvalidArgumentException('Date/time value must implement DateTimeInterface.');
        }

        $type = (string) ($options['type'] ?? 'datetime');

        $format = $options['format_to'] ?? match ($type) {
            'date' => 'Y-m-d',
            'time' => 'H:i:s',
            default => $this->format,
        };

        return $value->format((string) $format);
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid date/time value.');
        }

        $class = $options['dateTimeClass'] ?? $this->dateTimeClass;
        $formatFrom = $options['format_from'] ?? null;

        // Если задан format_from, используем createFromFormat.
        if (is_string($formatFrom) && $formatFrom !== '') {
            // DateTimeImmutable::createFromFormat возвращает false при ошибке.
            $dt = DateTimeImmutable::createFromFormat($formatFrom, $value);
            if ($dt === false) {
                throw new InvalidArgumentException('Failed to parse date/time using format_from.');
            }

            // Приводим к нужному классу, если требуется.
            if ($class === DateTimeImmutable::class) {
                return $dt;
            }

            try {
                return new $class($dt->format(DateTimeInterface::ATOM));
            } catch (Throwable $e) {
                throw new InvalidArgumentException('Failed to convert date/time to configured dateTimeClass.', 0, $e);
            }
        }

        try {
            return new $class($value);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Failed to parse date/time.', 0, $e);
        }
    }

    public function cast(mixed $value): mixed
    {
        return $this->castFrom($value);
    }
}
