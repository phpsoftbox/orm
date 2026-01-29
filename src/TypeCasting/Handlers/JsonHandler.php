<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\Orm\TypeCasting\Contracts\OrmTypeHandlerInterface;

final class JsonHandler implements OrmTypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'json';
    }

    public function castTo(mixed $value, array $options = []): int|float|string|bool|null
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException('JSON value must be array|string|null.');
        }

        $flags = (int) ($options['json_encode_flags'] ?? (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $json = json_encode($value, $flags);
        if ($json === false) {
            throw new InvalidArgumentException('Failed to encode JSON.');
        }

        return $json;
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid JSON value.');
        }

        $flags = (int) ($options['json_decode_flags'] ?? 0);

        $decoded = json_decode($value, true, flags: $flags);

        if (!is_array($decoded)) {
            $policy = (string) ($options['invalid_json'] ?? 'empty');

            return match ($policy) {
                'null' => null,
                'throw' => throw new InvalidArgumentException('Invalid JSON string.'),
                default => [],
            };
        }

        return $decoded;
    }

    public function cast(mixed $value): mixed
    {
        return $this->castFrom($value);
    }
}
