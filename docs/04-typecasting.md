# TypeCasting

## Цель

Type casting нужен, чтобы код работал с удобными PHP-типами.

## Поддерживаемые типы (на текущем этапе)

- `uuid`
- `json`
- `datetime` / `date` / `time`
- `bool` / `boolean`
- `decimal` (в PHP возвращаем string, чтобы не терять точность)
- `enum` (BackedEnum)
- `pg_array` (PostgreSQL array literal)

## Архитектура

В ORM используется `OrmTypeCaster`, который поддерживает преобразования в обе стороны:
- `castFrom(type, value, options)` — из скаляра/строки в PHP-тип
- `castTo(type, value, options)` — из PHP-типа в скаляр/строку

Дополнительно используется `TypeCastOptionsManager`:
- хранит дефолтные опции для типа
- принимает типизированные опции из `#[Column(options: ...)]`
- собирает итоговый массив options, который передаётся в handler

## Типизированные опции в #[Column]

Опции задаются объектами по строгому соглашению именования:
- `DatetimeCastOptions`
- `JsonCastOptions`
- `BoolCastOptions`
- `DecimalCastOptions`
- `EnumCastOptions`
- `PgArrayCastOptions`

Примеры:

### Datetime

```php
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\TypeCasting\Options\DatetimeCastOptions;

#[Column(type: 'datetime', options: new DatetimeCastOptions(
    // отдельные форматы "туда" и "обратно"
    formatTo: 'Y-m-d H:i:s',
    formatFrom: 'Y-m-d H:i:s',
    dateTimeClass: \DateTimeImmutable::class,
))]
public \DateTimeImmutable $created;
```

### Enum

```php
use PhpSoftBox\Orm\TypeCasting\Options\EnumCastOptions;

#[Column(type: 'enum', options: new EnumCastOptions(enumClass: StatusEnum::class))]
public StatusEnum $status;
```

### PgArray

```php
use PhpSoftBox\Orm\TypeCasting\Options\PgArrayCastOptions;

#[Column(type: 'pg_array', options: new PgArrayCastOptions(itemType: 'int'))]
public array $ids;
```

## Переопределение дефолтов (DI)

### Вариант: через PHP-DI

`AutoEntityMapper` в DI-готовом варианте принимает `TypeCastOptionsManager` извне.

```php
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\TypeCasting\Contracts\OrmTypeCasterInterface;
use PhpSoftBox\Orm\TypeCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\TypeCasting\Options\DatetimeCastOptions;

return [
    TypeCastOptionsManager::class => static function () {
        $m = new TypeCastOptionsManager();

        // пример: глобально сериализуем datetime в ISO-8601
        $m->registerDefaults('datetime', new DatetimeCastOptions(formatTo: DATE_ATOM));

        return $m;
    },

    AutoEntityMapper::class => static function ($c) {
        return new AutoEntityMapper(
            metadata: $c->get(MetadataProviderInterface::class),
            typeCaster: $c->get(OrmTypeCasterInterface::class),
            optionsManager: $c->get(TypeCastOptionsManager::class),
        );
    },
];
```
