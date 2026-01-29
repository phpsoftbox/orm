<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Options;

final readonly class JsonCastOptions implements TypeCastingOptionsInterface
{
    public function __construct(
        public ?int $jsonEncodeFlags = null,
        public ?int $jsonDecodeFlags = null,
        public JsonInvalidPolicy $invalidJson = JsonInvalidPolicy::Empty,
    ) {
    }

    public function toArray(): array
    {
        return [
            'json_encode_flags' => $this->jsonEncodeFlags,
            'json_decode_flags' => $this->jsonDecodeFlags,
            'invalid_json'      => $this->invalidJson->value,
        ];
    }
}
