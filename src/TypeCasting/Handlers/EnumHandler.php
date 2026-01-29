<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use BackedEnum;
use InvalidArgumentException;
use PhpSoftBox\Orm\TypeCasting\Contracts\OrmTypeHandlerInterface;

use function is_string;

/**
 * Enum handler: string/int из БД <-> BackedEnum в PHP.
 *
 * Требует опцию enum_class.
 */
final class EnumHandler implements OrmTypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'enum';
    }

    public function castTo(mixed $value, array $options = []): int|float|string|bool|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_string($value) || is_int($value)) {
            return $value;
        }

        throw new InvalidArgumentException('Invalid enum value.');
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $enumClass = $options['enum_class'] ?? null;
        if (!is_string($enumClass) || $enumClass === '') {
            throw new InvalidArgumentException('Enum cast requires option "enum_class".');
        }

        $nullOnInvalid = (bool) ($options['null_on_invalid'] ?? false);

        if (!is_string($value) && !is_int($value)) {
            if ($nullOnInvalid) {
                return null;
            }
            throw new InvalidArgumentException('Invalid enum backing value.');
        }

        try {
            /** @var BackedEnum $enum */
            $enum = $enumClass::from($value);
            return $enum;
        } catch (\ValueError $e) {
            if ($nullOnInvalid) {
                return null;
            }
            throw new InvalidArgumentException('Invalid enum backing value.', 0, $e);
        }
    }

    public function cast(mixed $value): mixed
    {
        // Без enum_class смысла нет.
        throw new InvalidArgumentException('Enum handler requires castFrom/castTo with options.');
    }
}

