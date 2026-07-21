<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Inflector;

use PhpSoftBox\Inflector\Contracts\InflectorInterface as BaseInflectorInterface;
use PhpSoftBox\Orm\Contracts\InflectorInterface;

/**
 * Адаптер, чтобы ORM зависела от собственного контракта, а не напрямую от пакета Inflector.
 */
final readonly class PhpSoftBoxInflectorAdapter implements InflectorInterface
{
    public function __construct(
        private BaseInflectorInterface $inflector,
    ) {
    }

    public function pluralize(string $word): string
    {
        return $this->inflector->pluralize($word);
    }

    public function singularize(string $word): string
    {
        return $this->inflector->singularize($word);
    }

    public function tableize(string $word): string
    {
        return $this->inflector->tableize($word);
    }

    public function classify(string $word): string
    {
        return $this->inflector->classify($word);
    }

    public function camelize(string $word): string
    {
        return $this->inflector->camelize($word);
    }

    public function capitalize(string $string, string $delimiters = " \n\t\r\0\x0B-"): string
    {
        return $this->inflector->capitalize($string, $delimiters);
    }

    public function urlize(string $string): string
    {
        return $this->inflector->urlize($string);
    }
}
