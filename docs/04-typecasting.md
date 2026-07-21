# TypeCasting

## Цель

Type casting нужен, чтобы код работал с удобными PHP-типами.

## Поддерживаемые типы (на текущем этапе)

- `uuid`
- `json`
- `datetime` / `date` / `time`
- `date_point` / `day_point` / `time_point` (DatePoint + Clock)
- `bool` / `boolean`
- `decimal` (в PHP возвращаем string, чтобы не терять точность)
- `money` (в PHP возвращаем нормализованную строку, в БД храним копейки)
- `enum` (BackedEnum)
- `pg_array` (PostgreSQL array literal)
- `phone`
- `storage_path` (StoragePath + URL, требует регистрации handler)

## Архитектура

В ORM используется `TypeCaster` из `DataCasting`, который поддерживает преобразования в обе стороны:
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
use PhpSoftBox\DataCasting\Options\DatetimeCastOptions;
use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\Orm\Metadata\Attributes\Column;

final class AuditEntry
{
    #[Column(type: 'datetime', options: new DatetimeCastOptions(
        // отдельные форматы "туда" и "обратно"
        formatTo: 'Y-m-d H:i:s',
        formatFrom: 'Y-m-d H:i:s',
        dateTimeClass: \DateTimeImmutable::class,
    ))]
    public \DateTimeImmutable $createdAt;

    // Clock-aware datetime без отдельного типа:
    #[Column(type: 'datetime', options: new DatetimeCastOptions(
        formatTo: 'Y-m-d H:i:s',
        formatFrom: 'Y-m-d H:i:s',
        dateTimeClass: DatePoint::class,
    ))]
    public DatePoint $occurredAt;

    public function __construct(
        \DateTimeImmutable $createdAt,
        DatePoint $occurredAt,
    ) {
        $this->createdAt  = $createdAt;
        $this->occurredAt = $occurredAt;
    }
}
```

### DatePoint (Clock-aware)

```php
use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\Orm\Metadata\Attributes\Column;

final class Schedule
{
    #[Column(type: 'day_point')]
    public DatePoint $birthday;

    #[Column(type: 'time_point')]
    public DatePoint $opensAt;

    public function __construct(DatePoint $birthday, DatePoint $opensAt)
    {
        $this->birthday = $birthday;
        $this->opensAt  = $opensAt;
    }
}
```

Обычное обязательное поле является частью состояния entity и передаётся через
конструктор. Не оставляйте такое mapped property неинициализированным: при извлечении
state ORM выбросит `UninitializedMappedPropertyException` с именем entity и свойства.

### Timestamps, управляемые ORM

`createdDatetime` и `updatedDatetime` имеют другое время жизни. У transient entity
они ещё не существуют, а перед `INSERT` их заполняет `TimestampsListener`:

```php
#[Column(name: 'created_datetime', type: 'date_point')]
public ?DatePoint $createdDatetime = null;

#[Column(name: 'updated_datetime', type: 'date_point')]
public ?DatePoint $updatedDatetime = null;
```

Nullable PHP-тип здесь описывает только transient-состояние до первого `flush()`.
`#[Column]` остаётся с `nullable: false` по умолчанию, а колонки БД — `NOT NULL`.
После `OnCreate` ORM проверяет итоговый state и не выполняет `INSERT`, если listener
не заполнил обязательное значение.

При `UPDATE` listener заменяет `updatedDatetime`. Устанавливать ORM-managed timestamp
в конструкторе не нужно: это смешивает время создания PHP-объекта со временем записи
в БД, а listener всё равно является источником итогового значения.

`new DatePoint()` и `DatePoint::now()` нельзя использовать как initializer обычного
property: PHP разрешает там только constant expression.

`DatePoint` использует `Clock::now()` при создании без аргументов.
В тестах фиксируйте время через `Clock::freeze(...)`.

### Правило
Если нужна поддержка заморозки времени (frozen time), используйте:
- `type: date_point/day_point/time_point`, или
- `type: datetime` + `dateTimeClass: DatePoint::class`.

Если подставить другой класс (например, Carbon), то он **не будет** использовать `Clock`,
и фриз перестанет работать, пока вы не реализуете свою обёртку, читающую `Clock`.

### Как использовать в тестах

```php
use PhpSoftBox\Clock\Clock;

Clock::freeze(new \DateTimeImmutable('2024-01-01 00:00:00'));

// любое DatePoint::now() или поле date_point будет использовать зафризенное время
$now = Clock::now();

Clock::reset();
```

### JSON: массивы и типизированные объекты

Обычный `array` остаётся базовым и обратно совместимым представлением JSON:

```php
#[Column(type: 'json', nullable: true)]
public ?array $metadata = null;
```

Если свойство типизировано классом, ORM выводит target class из PHP-типа и рекурсивно
гидратирует JSON через конструктор:

```php
final readonly class ShipmentMetadata
{
    public function __construct(
        public string $source,
        public AddressMetadata $address,
        public ?string $comment,
    ) {
    }
}

#[Column(type: 'json', nullable: true)]
public ?ShipmentMetadata $metadata = null;
```

Правила object hydration:

- отсутствующее non-nullable поле вызывает `JsonHydrationException`;
- отсутствующее nullable поле получает default конструктора или `null`;
- `null` для non-nullable поля и несовместимый scalar type отклоняются;
- неизвестные поля отклоняются, чтобы обратная запись не теряла данные незаметно;
- вложенные class-typed параметры гидратируются рекурсивно;
- exception содержит полный путь, например `$.metadata.items[2].sku`.

#### Списки и ассоциативные карты DTO

PHP не хранит item type у `array`, поэтому он задаётся отдельным атрибутом:

```php
use PhpSoftBox\DataCasting\Attributes\JsonCollectionOf;
use PhpSoftBox\DataCasting\Attributes\JsonMapOf;

final readonly class ShipmentMetadata
{
    /**
     * @param list<ShipmentItemMetadata> $items
     * @param array<string, ShipmentItemMetadata> $itemsBySku
     */
    public function __construct(
        #[JsonCollectionOf(ShipmentItemMetadata::class)]
        public array $items,
        #[JsonMapOf(ShipmentItemMetadata::class)]
        public array $itemsBySku,
    ) {
    }
}
```

Атрибуты работают и непосредственно на JSON-колонке:

```php
#[Column(type: 'json')]
#[JsonCollectionOf(ShipmentItemMetadata::class)]
public array $items = [];

#[Column(type: 'json')]
#[JsonMapOf(ShipmentItemMetadata::class)]
public array $itemsBySku = [];
```

`JsonCollectionOf` требует JSON list и сохраняет `[]`. `JsonMapOf` требует JSON object
и сериализует даже пустую PHP-карту как `{}`.

#### JsonSerializable и custom hydration

При записи объекта ORM использует стандартный `JsonSerializable`, если DTO его реализует.
Без интерфейса применяется автоматическая нормализация constructor-promoted свойств.

Если JSON-форма не совпадает с параметрами конструктора, обратное преобразование можно
описать через `JsonHydratableInterface`:

```php
final readonly class Metadata implements JsonSerializable, JsonHydratableInterface
{
    public function __construct(public string $name)
    {
    }

    public static function fromJsonData(array $data): static
    {
        return new self($data['stored_name']);
    }

    public function jsonSerialize(): array
    {
        return ['stored_name' => $this->name];
    }
}
```

#### Полиморфные DTO и фабрики

Если реализация зависит от JSON или другой колонки исходной строки, используется фабрика:

```php
use PhpSoftBox\DataCasting\Attributes\JsonFactory;

#[Column(type: 'json')]
#[JsonFactory(ShipmentMetadataFactory::class)]
public ShipmentMetadataInterface $metadata;
```

```php
final class ShipmentMetadataFactory implements JsonValueFactoryInterface
{
    public function create(array $data, JsonHydrationContext $context): object
    {
        return match ($context->source['shipment_type']) {
            'fbo' => new FboShipmentMetadata($data['code']),
            'fbs' => new FbsShipmentMetadata($data['code']),
        };
    }
}
```

`JsonHydrationContext::source` содержит всю исходную строку до приведения типов ORM. Ключи
в ней — имена колонок результата запроса, а не обязательно имена PHP-свойств. Типы значений
зависят от DBAL-драйвера и не должны восприниматься как типы свойств entity. Поэтому выбор
DTO не зависит от порядка гидрации полей, но фабрика должна явно нормализовать используемые
значения.

По умолчанию фабрика создаётся без аргументов. Фабрики с зависимостями подключаются через
`JsonValueFactoryResolverInterface`, переданный в `DefaultTypeCasterFactory`:

```php
$typeCaster = new DefaultTypeCasterFactory(
    jsonValueFactoryResolver: $containerFactoryResolver,
)->create();
```

Resolver должен вернуть экземпляр `JsonValueFactoryInterface` из DI-контейнера. Результат
фабрики всегда проверяется на соответствие объявленному типу свойства.

### Enum

```php
use PhpSoftBox\DataCasting\Options\EnumCastOptions;

#[Column(type: 'enum', options: new EnumCastOptions(enumClass: StatusEnum::class))]
public StatusEnum $status;
```

### Money

```php
use PhpSoftBox\DataCasting\Options\MoneyCastOptions;

#[Column(type: 'money', options: new MoneyCastOptions(
    scale: 2,
    trimTrailingZeros: false,
))]
public string $price;
```

### PgArray

```php
use PhpSoftBox\DataCasting\Options\PgArrayCastOptions;

#[Column(type: 'pg_array', options: new PgArrayCastOptions(itemType: 'int'))]
public array $ids;
```

### Phone

```php
use PhpSoftBox\DataCasting\Options\PhoneCastOptions;

#[Column(type: 'phone', options: new PhoneCastOptions(
    withCountryCodeTo: false,
    withCountryCodeFrom: true,
))]
public string $phone;
```

### Storage Path

```php
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\DataCasting\Options\StoragePathCastOptions;

#[Column(type: 'storage_path', length: 255, nullable: true, options: new StoragePathCastOptions(disk: 'public'))]
public mixed $avatarPath = null;
```

Чтобы `storage_path` работал, нужно:
1) зарегистрировать handler (в приложении, где есть storage)
2) передать `Storage` в defaults:

```php
use PhpSoftBox\DataCasting\Handlers\StoragePathHandler;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\DataCasting\Options\StoragePathCastOptions;
use PhpSoftBox\Storage\Storage;

$typeCaster->registerHandler(new StoragePathHandler());

$options = new TypeCastOptionsManager();
$options->registerDefaults('storage_path', new StoragePathCastOptions(
    storage: $container->get(Storage::class),
    // disk: 'local', // опционально
));
```

## Переопределение дефолтов (DI)

### Вариант: через PHP-DI

`AutoEntityMapper` в DI-готовом варианте принимает `TypeCastOptionsManager` извне.

```php
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\DataCasting\Contracts\TypeCasterInterface;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\DataCasting\Options\DatetimeCastOptions;

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
            typeCaster: $c->get(TypeCasterInterface::class),
            optionsManager: $c->get(TypeCastOptionsManager::class),
        );
    },
];
```
