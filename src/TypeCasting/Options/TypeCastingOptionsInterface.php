<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Options;

/**
 * Маркерный интерфейс для типизированных опций кастинга.
 */
interface TypeCastingOptionsInterface
{
    /**
     * Преобразует объект опций в массив (для передачи в handler).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
