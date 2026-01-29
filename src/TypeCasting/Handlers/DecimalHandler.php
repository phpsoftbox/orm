<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\Orm\TypeCasting\Contracts\OrmTypeHandlerInterface;

use function is_int;
use function is_float;
use function is_string;

/**
 * Decimal handler.
 *
 * Мы не используем float из-за потери точности.
 * В PHP возвращаем string либо null.
 */
final class DecimalHandler implements OrmTypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'decimal';
    }

    public function castTo(mixed $value, array $options = []): int|float|string|bool|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }

        // тут можно дальше добавить валидацию/нормализацию
        return $this->applyOptions($value, $options);
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $this->applyOptions((string) $value, $options);
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }

        return $this->applyOptions($value, $options);
    }

    public function cast(mixed $value): mixed
    {
        return $this->castFrom($value);
    }

    private function applyOptions(string $value, array $options): string
    {
        $trimZeros = (bool) ($options['trim_trailing_zeros'] ?? false);

        // ВАЖНО: scale-округление не делаем автоматически без bcmath/brick, чтобы не терять точность.

        if ($trimZeros && str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value;
    }
}
