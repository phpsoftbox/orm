<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Options;

final readonly class EncryptedCastOptions implements TypeCastingOptionsInterface
{
    public function __construct(
        public string $key,
    ) {
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
        ];
    }
}

