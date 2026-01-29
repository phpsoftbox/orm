<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\TypeCasting\Options;

use PhpSoftBox\Orm\TypeCasting\Options\BoolCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\DatetimeCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\DecimalCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\JsonCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\PgArrayCastOptions;
use PhpSoftBox\Orm\TypeCasting\Options\TypeCastingOptionsInterface;

/**
 * Менеджер опций: позволяет задавать дефолты и резолвить опции по type.
 *
 * Идея: типизированные опции задаются в #[Column(options: ...)] как объект.
 * При резолве мы:
 *  - берём дефолтные опции для типа
 *  - поверх применяем опции из атрибута
 *  - получаем итоговый массив options для handler'а
 */
final class TypeCastOptionsManager
{
    /**
     * @var array<string, TypeCastingOptionsInterface>
     */
    private array $defaults = [];

    public function __construct()
    {
        // Базовые дефолты (можно переопределять через registerDefaults()).
        $this->defaults['datetime'] = new DatetimeCastOptions();
        $this->defaults['date'] = new DatetimeCastOptions(formatTo: 'Y-m-d', formatFrom: 'Y-m-d');
        $this->defaults['time'] = new DatetimeCastOptions(formatTo: 'H:i:s', formatFrom: 'H:i:s');
        $this->defaults['json'] = new JsonCastOptions();

        $this->defaults['bool'] = new BoolCastOptions();
        $this->defaults['boolean'] = new BoolCastOptions();

        $this->defaults['decimal'] = new DecimalCastOptions();

        $this->defaults['pg_array'] = new PgArrayCastOptions();

        // enum/encrypted имеют обязательные параметры (enum_class/key), поэтому их обычно задают в #[Column].
        // Но дефолтные опции всё равно можно зарегистриров��ть через DI.
    }

    public function registerDefaults(string $type, TypeCastingOptionsInterface $defaults): void
    {
        $this->defaults[$type] = $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $type, ?TypeCastingOptionsInterface $overrides): array
    {
        $base = $this->defaults[$type] ?? null;

        $baseArray = $base?->toArray() ?? [];
        $overridesArray = $overrides?->toArray() ?? [];

        // overrides выигрывают
        return array_filter(
            [...$baseArray, ...$overridesArray],
            static fn(mixed $v): bool => $v !== null,
        );
    }
}
