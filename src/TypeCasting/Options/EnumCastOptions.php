<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Options;

use BackedEnum;

final readonly class EnumCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param class-string<BackedEnum> $enumClass
     */
    public function __construct(
        public string $enumClass,
        public bool $nullOnInvalid = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'enum_class'      => $this->enumClass,
            'null_on_invalid' => $this->nullOnInvalid,
        ];
    }
}
