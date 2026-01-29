<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Options;

final readonly class DecimalCastOptions implements TypeCastingOptionsInterface
{
    public function __construct(
        public ?int $scale = null,
        public bool $trimTrailingZeros = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'scale'               => $this->scale,
            'trim_trailing_zeros' => $this->trimTrailingZeros,
        ];
    }
}
