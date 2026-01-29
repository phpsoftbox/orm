<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use function iconv;
use function preg_replace;
use function strtolower;
use function trim;

final class Slugifier
{
    public function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Транслитерация (если доступна). Если iconv нет/не смог — просто оставляем строку.
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = strtolower($value);

        // Заменяем всё, что не буква/цифра, на дефис.
        $value = (string) preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim($value, '-');

        return $value;
    }
}

