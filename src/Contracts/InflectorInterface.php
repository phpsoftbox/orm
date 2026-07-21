<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

/**
 * Локальный интерфейс-обёртка над инфлектором.
 *
 * Зачем он нужен:
 * - ORM не должен зависеть от конкретной реализации инфлектора;
 * - можно подменить реализацию в приложении через DI.
 */
interface InflectorInterface
{
    public function pluralize(string $word): string;

    public function singularize(string $word): string;

    public function tableize(string $word): string;

    public function classify(string $word): string;

    public function camelize(string $word): string;

    public function capitalize(string $string, string $delimiters = " \n\t\r\0\x0B-"): string;

    public function urlize(string $string): string;
}
