<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Options;

final readonly class BoolCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param list<int|string|bool> $trueValues
     * @param list<int|string|bool> $falseValues
     */
    public function __construct(
        public array $trueValues = [true, 1, '1', 'true', 't', 'yes', 'y', 'on'],
        public array $falseValues = [false, 0, '0', 'false', 'f', 'no', 'n', 'off', ''],
        public bool $strict = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'true_values' => $this->trueValues,
            'false_values' => $this->falseValues,
            'strict' => $this->strict,
        ];
    }
}
