<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\Orm\TypeCasting\Contracts\OrmTypeHandlerInterface;

use function array_map;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function strlen;
use function substr;
use function trim;

/**
 * PostgreSQL array.
 *
 * В БД приходит строкой вида: {a,b,c} или {"a,b",c}
 * В PHP возвращаем list<mixed>.
 */
final class PgArrayHandler implements OrmTypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'pg_array';
    }

    public function castTo(mixed $value, array $options = []): int|float|string|bool|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException('PgArray value must be array|null.');
        }

        // Простой encoder: экранируем кавычки/обратные слеши, оборачиваем строковые элементы в двойные кавычки при необходимости.
        $parts = [];
        foreach ($value as $item) {
            if ($item === null) {
                $parts[] = 'NULL';
                continue;
            }

            if (is_bool($item)) {
                $parts[] = $item ? 't' : 'f';
                continue;
            }

            if (is_int($item) || is_float($item)) {
                $parts[] = (string) $item;
                continue;
            }

            $s            = (string) $item;
            $needsQuoting = str_contains($s, ',') || str_contains($s, '"') || str_contains($s, '{') || str_contains($s, '}') || str_contains($s, ' ');
            $s            = str_replace('\\', '\\\\', $s);
            $s            = str_replace('"', '\\"', $s);

            $parts[] = $needsQuoting ? '"' . $s . '"' : $s;
        }

        return '{' . implode(',', $parts) . '}';
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
            throw new InvalidArgumentException('PgArray value must be string|array|null.');
        }

        if ($value === '') {
            return ($options['empty_string_as_empty_array'] ?? true) ? [] : null;
        }

        if ($value[0] !== '{' || !str_ends_with($value, '}')) {
            throw new InvalidArgumentException('Invalid pg array literal.');
        }

        $inner = substr($value, 1, -1);
        if ($inner === '') {
            return [];
        }

        $items = $this->parseArrayInner($inner);

        // item_type каст применяем поверх
        $itemType = $options['item_type'] ?? null;
        if (!is_string($itemType) || $itemType === '') {
            return $items;
        }

        return array_map(static function (mixed $v) use ($itemType) {
            if ($v === null) {
                return null;
            }

            return match ($itemType) {
                'int'   => (int) $v,
                'float' => (float) $v,
                'bool'  => ($v === 't' || $v === 'true' || $v === '1' || $v === 1),
                default => (string) $v,
            };
        }, $items);
    }

    public function cast(mixed $value): mixed
    {
        return $this->castFrom($value);
    }

    /**
     * @return list<string|null>
     */
    private function parseArrayInner(string $inner): array
    {
        $result   = [];
        $len      = strlen($inner);
        $buf      = '';
        $inQuotes = false;
        $escape   = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $inner[$i];

            if ($escape) {
                $buf .= $ch;
                $escape = false;
                continue;
            }

            if ($inQuotes && $ch === '\\') {
                $escape = true;
                continue;
            }

            if ($ch === '"') {
                $inQuotes = !$inQuotes;
                continue;
            }

            if (!$inQuotes && $ch === ',') {
                $result[] = $this->normalizeToken($buf);
                $buf      = '';
                continue;
            }

            $buf .= $ch;
        }

        $result[] = $this->normalizeToken($buf);

        return $result;
    }

    private function normalizeToken(string $token): ?string
    {
        $token = trim($token);
        if ($token === 'NULL') {
            return null;
        }

        return $token;
    }
}
