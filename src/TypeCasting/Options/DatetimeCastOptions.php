<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Options;

use DateTimeImmutable;
use DateTimeInterface;

final readonly class DatetimeCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param string|null $formatTo Формат даты для преобразования.
     * @param string|null $formatFrom Формат даты для обратного преобразования.
     * @param class-string<DateTimeInterface> $dateTimeClass
     */
    public function __construct(
        public ?string $formatTo = null,
        public ?string $formatFrom = null,
        public string $dateTimeClass = DateTimeImmutable::class,
    ) {
    }

    public function toArray(): array
    {
        return [
            'format_to' => $this->formatTo,
            'format_from' => $this->formatFrom,
            'dateTimeClass' => $this->dateTimeClass,
        ];
    }
}
