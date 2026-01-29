# Атрибуты и метаданные

## Зачем это нужно

Атрибуты позволяют описать маппинг сущности на таблицу декларативно.

Это нужно для:
- auto-hydration / auto-extract
- автоматического определения primary key
- type casting'а (uuid/datetime/json/enum/value objects)
- декларативных связей (relations)

## Конвенции именования (важно)

В PhpSoftBox ORM принято:
- **свойства сущностей** — `camelCase`
- **колонки в БД** — `snake_case`

Пример типичного поля внешнего ключа:

```php
public int $authorId; // свойство сущности
// колонка в БД: author_id
```

Чтобы это работало предсказуемо, рекомендуется **явно** указывать имя колонки:

```php
#[Column(name: 'author_id', type: 'int')]
public int $authorId;
```

> Важно: параметры отношений `joinColumn`/`localKey`/`parentKey` и т.п. — это **имена свойств сущности** (camelCase),
> а не имена колонок таблицы.
> Имена колонок используются в репозиториях/SQL и задаются через `#[Column(name: ...)]`.

## Атрибуты

### #[Entity]

```php
#[Entity(table: 'users', connection: 'main')]
final class User {}
```

Параметры:
- `table` — имя таблицы (обязательно)
- `connection` — имя подключения (опционально)
- `repository` — класс репозитория (опционально)
- `repositoryNamespace` — неймспейс для авто-резолва репозитория (по умолчанию `Repository`)

### #[Column]

```php
#[Column(type: 'string', length: 255)]
public string $name;
```

### #[Id]

Маркирует свойство как primary key.

### #[GeneratedValue]

```php
#[GeneratedValue(strategy: 'uuid')] // auto|uuid|none
```

### #[NotMapped]

Исключает свойство из маппинга в БД.

### Relations (связи)

Для связей используются атрибуты из `PhpSoftBox\Orm\Metadata\Attributes`.

#### #[BelongsTo]

`#[BelongsTo]` — рекомендуемый синтаксис для связи **many-to-one**.

Важно:
- `joinColumn` — это **имя свойства сущности** (camelCase), например `authorId`
- если `joinColumn` не задан, ORM пытается вывести его по конвенции: `<имя_связи>Id`
  - пример: связь `author` -> `authorId`
- имя колонки в БД задаётся через `#[Column(name: ...)]`, например `author_id`
- `referencedColumn` по умолчанию равен `id`
- нельзя ставить `#[BelongsTo]` и `#[ManyToOne]` одновременно на одно свойство (будет исключение)

Пример:

```php
use PhpSoftBox\Orm\Metadata\Attributes\BelongsTo;
use PhpSoftBox\Orm\Metadata\Attributes\Column;

#[Column(name: 'author_id', type: 'int')]
public int $authorId;

// joinColumn не указываем: будет выведен как authorId
#[BelongsTo(targetEntity: Author::class)]
public ?Author $author = null;
```

#### #[ManyToOne]

Низкоуровневый эквивалент `BelongsTo` (оставлен для совместимости/явной настройки):

```php
use PhpSoftBox\Orm\Metadata\Attributes\ManyToOne;

#[ManyToOne(targetEntity: Author::class, joinColumn: 'authorId', referencedColumn: 'id')]
public ?Author $author = null;
```

#### #[HasOne] / #[HasMany]

Для связей one-to-one и one-to-many можно не указывать `foreignKey`, если используется стандартный стиль имён колонок.

По умолчанию:
- `localKey = 'id'`
- `foreignKey = <snake_case(имя свойства связи)>_id`
  - пример: `post -> post_id`

Если у вас другая схема (например `post_uuid`, `owner_id`, или ключ не `id`) —
укажите `foreignKey` и/или `localKey` вручную.

Пример:

```php
use PhpSoftBox\Orm\Metadata\Attributes\HasMany;

#[HasMany(targetEntity: Comment::class)]
public EntityCollection $post; // foreignKey будет post_id
```

## MetadataProvider

Слой `MetadataProviderInterface` читает атрибуты через Reflection и кеширует результат.

На текущем этапе реализация: `AttributeMetadataProvider`.

## Inflector и NamingConvention

### Зачем нужен Inflector

`phpsoftbox/inflector` используется ORM, чтобы:
- переводить имя класса `UserProfile` в `user_profile`
- получать множественное число для имён таблиц: `user` -> `users`
- строить имена внешних ключей/связей по конвенциям: `post` -> `post_id`

Именно Inflector помогает сделать так, чтобы большую часть метаданных можно было **не дублировать** в атрибутах,
если вы придерживаетесь одного стиля.

### NamingConventionInterface

ORM не жёстко «зашита» на конкретные правила. Все правила, связанные с auto-guess, вынесены в `NamingConventionInterface`.
Дефолтная реализация — `InflectorNamingConvention`.

### Конфигурация через EntityManagerConfig

`EntityManager` автоматически создаёт `AttributeMetadataProvider` и прокидывает в него `namingConvention` из конфигурации.
Это важно для всех defaults (table, joinColumn, pivotTable и т.д.).

Пример:

```php
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\EntityManagerConfig;

$em = new EntityManager(
    connection: $conn,
    config: new EntityManagerConfig(
        enableBuiltInListeners: false,
        // Можно подменить namingConvention на свою реализацию
        // namingConvention: new MyNamingConvention(...),
    ),
);
```

### Как namingConvention влияет на defaults

- Если в `#[Entity]` не задан `table`, ORM вычисляет таблицу из short-name класса:
  - `User` -> `users`
  - `BlogPost` -> `blog_posts`

- Для `#[BelongsTo]`/`#[ManyToOne]`, если не указан `joinColumn`, он выводится как `<relation>Id`:
  - `author` -> `authorId` (это **имя свойства**, не имя колонки)

- Для `#[HasOne]`/`#[HasMany]`, если не указан `foreignKey`, он выводится как `<snake_case(relation)>_id`:
  - `post` -> `post_id`
  - `blogPost` -> `blog_post_id`

- Для `#[BelongsToMany]`, если не указаны `pivotTable/foreignPivotKey/relatedPivotKey`, они строятся из таблиц:
  - `users` + `roles` -> `user_roles`
  - `users` -> `user_id`, `roles` -> `role_id`

> Проще говоря: если у сущности указан `#[Entity(table: '...')]`, то при вычислении defaults ORM
> использует **этот table**.
> Если `table` не задан, ORM выводит имя таблицы по конвенции из имени класса (например `User` -> `users`).

### Best practice

- Явно задавайте `#[Entity(table: ...)]` для «важных» сущностей (особенно если таблицы не совпадают с конвенциями)
- Явно задавайте `#[Column(name: ...)]` для внешних ключей (`author_id`, `user_id`), чтобы не зависеть от guess'а
- Параметры связей (`joinColumn`, `localKey`, `parentKey`) — это **имена свойств** (camelCase), а не имена колонок
