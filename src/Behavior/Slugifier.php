<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Behavior;

use function iconv;
use function is_string;
use function mb_strtolower;
use function preg_replace;
use function strtolower;
use function strtr;
use function trim;

final class Slugifier
{
    public function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = strtr($value, $this->transliterationMap());

        // Транслитерация для символов вне базовой карты. Если iconv нет/не смог — оставляем строку.
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

    /**
     * @return array<string, string>
     */
    private function transliterationMap(): array
    {
        return [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sch',
            'ъ' => '',
            'ы' => 'y',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
            '+' => 'plus',
            '&' => 'and',
        ];
    }
}
