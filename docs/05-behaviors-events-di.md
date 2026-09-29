# Behaviors, события и DI

## События ORM

EntityManager вызывает события во время `flush()`:

- `OnCreate` / `AfterCreate`
- `OnUpdate` / `AfterUpdate`
- `OnRestore` / `AfterRestore`
- `OnDelete` / `AfterDelete`
- `OnForceDelete` / `AfterForceDelete`

Событие содержит:
- `entity()` — сущность
- `state()` — изменяемое состояние данных (можно менять то, что уйдёт в INSERT/UPDATE)
- `orm()` — ссылка на `EntityManagerInterface`

Что делает ORM с `state()` в каждом событии:

| Событие | Что уходит в запрос |
|---|---|
| `OnCreate` | всё состояние — INSERT |
| `OnUpdate` | колонки, изменённые относительно snapshot, и колонки, которые добавил/изменил слушатель — UPDATE |
| `OnRestore` | всё состояние — UPDATE |
| `OnDelete` | при `#[SoftDelete]` — колонки, которые изменил слушатель, в том же UPDATE; при физическом удалении игнорируется |
| `OnForceDelete` | ничего: запись удаляется физически |

## Обработчики событий и порядок вызова

Событие сущности получают (в этом порядке):

1. `#[Hook]` сущности — статический callable, объявленный атрибутом на классе сущности;
2. `#[EventListener]` сущности — только для событий **этой** сущности (listener другой сущности их не получает);
3. глобальный dispatcher (`events` в `EntityManager`) — listeners, зарегистрированные для всех сущностей;
4. встроенные listeners ORM (Sluggable, Timestamps), если включены.

```php
#[Entity(table: 'posts')]
#[Hook(callable: [self::class, 'beforeCreate'], events: [OnCreate::class])]
#[EventListener(listener: PostListener::class)]
final class Post implements EntityInterface
{
    public static function beforeCreate(OnCreate $event): void
    {
        // ...
    }
}
```

Исключение из hook или listener, а также ошибка создания listener пробрасываются из `flush()`
и откатывают его транзакцию. Hook, который не является callable, — ошибка конфигурации (`OrmException`).

### Какие методы listener'а вызываются

- метод с `#[Listen(Event::class)]` вызывается для перечисленных в атрибутах событий;
- публичный метод `on*(Event $event)` **без** `#[Listen]` вызывается по соглашению имени, если тип параметра
  совпадает с классом события;
- метод с `#[Listen]` по соглашению имени повторно не вызывается: каждый метод срабатывает на событие один раз.

### Создание `#[EventListener]` (DI)

Listener сущности создаётся один раз на `EntityManager` через `ListenerResolverInterface`:

- `DefaultListenerResolver` (по умолчанию) — `new $class()`; для listener с обязательными аргументами
  конструктора бросает `OrmException`;
- `ContainerListenerResolver` — берёт listener из PSR-11 контейнера (`$container->get($class)`), поэтому listener
  может иметь зависимости. Если контейнер не знает класс, используется `DefaultListenerResolver`.

```php
use PhpSoftBox\Orm\Behavior\ContainerListenerResolver;

$em = new EntityManager(
    connection: $conn,
    listenerResolver: new ContainerListenerResolver($container),
);
```

Для registry/фабрики тот же резолвер передаётся параметром `listenerResolver`
(см. [EntityManagerRegistry](08-entity-manager-registry.md)).

## Behaviors

Behaviors — это «глобальные» правила ORM.

Сейчас реализовано:

### SoftDelete

Атрибут на сущности:

```php
#[SoftDelete(entityField: 'deletedDatetime', column: 'deleted_datetime')]
```

- `delete()` делает UPDATE `deleted_datetime` вместо физического DELETE
- слушатель `OnDelete` может дописать к этому UPDATE свои колонки (например, `status`) через `state()->register()`
- `restore()` очищает `deleted_datetime` и возвращает запись в обычные выборки
- `forceDelete()` / `forceRemove()` физически удаляет запись, игнорируя SoftDelete
- `EntityManager::bulk()->remove()` делает set-based soft delete для нескольких записей
- `EntityManager::bulk()->restore()` делает set-based восстановление нескольких записей
- чтение по умолчанию скрывает удалённые записи
- changelog фиксирует восстановление как действие `restore`

Пример:

```php
$post = $em->findWithDeleted(Post::class, $id);

$em->restore($post);
$em->flush();
```

Для репозитория доступен прямой вариант:

```php
$post = $posts->findWithDeleted($id);

$posts->restore($post);
```

Если в процессе используется UnitOfWork, предпочитайте `EntityManager::findWithDeleted()`:
он учитывает IdentityMap и не создаёт второй экземпляр уже managed-сущности.

#### Изменение других колонок при мягком удалении

```php
final readonly class PostListener
{
    #[Listen(OnDelete::class)]
    public function onDelete(OnDelete $event): void
    {
        $event->state()->register('status', 'archived');
    }
}
```

`status` уйдёт в тот же `UPDATE`, что и `deleted_datetime`, то есть одним запросом и внутри транзакции `flush()`.
Правила:

- применяются только колонки, которые слушатель добавил или изменил: остальные поля сущности удаление не трогает;
- колонку soft delete задаёт сама ORM, переопределить её из состояния нельзя;
- колонки, которых нет в метаданных сущности или помеченные как необновляемые, игнорируются;
- если у сущности нет `#[SoftDelete]`, запись удаляется физически, и зарегистрированные колонки игнорируются
  (ошибки при этом не будет);
- добавленные колонки попадают в changelog как `after` для действия `delete`.

Для массовых операций то же самое делает слушатель `OnBulkRemove`:

```php
#[Listen(OnBulkRemove::class)]
public function onBulkRemove(OnBulkRemove $event): void
{
    $event->state()->register('status', 'archived');
}
```

### Sluggable

Атрибут на сущности:

```php
#[Sluggable(
    source: 'title',
    target: 'slug',
    prefix: '{id}-',
    postfix: '.html',
)]
```

На событиях `OnCreate` и `OnUpdate` (если `onUpdate=true`) ORM генерирует slug и пишет его в `state()`.
`source`, `target` и шаблоны задаются именами свойств сущности. Если имя колонки отличается,
ORM сам резолвит физическую колонку через `#[Column(name: ...)]` и синхронизирует значение
с целевым свойством сущности:

```php
#[Sluggable(source: 'title', target: 'seoSlug', prefix: '{id}-')]
final class Post
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[Column(name: 'headline', type: 'string')]
    public string $title;

    #[Column(name: 'seo_slug', type: 'string')]
    public ?string $seoSlug = null;

    public function __construct(int $id, string $title)
    {
        $this->id    = $id;
        $this->title = $title;
    }
}
```

`seoSlug` имеет nullable PHP-тип только в transient entity. `#[Column]` и колонка
БД остаются non-nullable: после `OnCreate` ORM проверит, что listener заполнил
обязательное значение. В отличие от listener-managed target, `id` и `title`
являются входными данными entity и задаются через конструктор.

## DI: как регистрировать listeners

Компонент ORM **не зависит** от DI-контейнеров.

Рекомендованный способ:
- создать listener-инстансы вашим контейнером
- передать их в `DefaultEventDispatcher`

Пример (PHP-DI):

```php
use PhpSoftBox\Orm\Behavior\DefaultEventDispatcher;
use PhpSoftBox\Orm\EntityManager;

$dispatcher = new DefaultEventDispatcher([
    $container->get(App\Listener\Orm\CommentListener::class),
]);

$em = new EntityManager(
    connection: $conn,
    events: $dispatcher,
);
```

Рекомендовано держать ORM-listeners отдельно от listeners фреймворка:
- `App\Listener\Orm\...`

## Конфигурация EntityManager и built-in behaviors

По умолчанию `EntityManager` подключает встроенные behaviors (например Sluggable). Они хранятся внутри
`EntityManager` и вызываются после глобального dispatcher; в переданный `events` они не регистрируются,
поэтому один dispatcher можно разделять между несколькими `EntityManager` (например, в registry).

### Что такое built-in behaviors/listeners

- **События** (`OnCreate`, `AfterCreate`, `OnUpdate`, ...)
  - это классы команд/ивентов, которые **всегда создаются** внутри `flush()`.
  - они будут созданы вне зависимости от того, включены built-in listeners или нет.

- **Built-in listeners/behaviors**
  - это обработчики, которые ORM регистрирует автоматически (если включено).
  - сейчас к ним относятся, например: `Sluggable` (генерация slug), `SoftDelete` (мягкое удаление).

### enableBuiltInListeners

Параметр `enableBuiltInListeners` отвечает только за **автоматическую регистрацию** встроенных listeners/behaviors.

- `enableBuiltInListeners: true` (по умолчанию) — ORM сама подключает встроенные behaviors.
- `enableBuiltInListeners: false` — ORM **не будет** автоматически подключать built-in behaviors.
  Это полезно в тестах или если вы хотите собрать behaviors вручную.

> Рекомендация: в реальном приложении обычно оставляют `enableBuiltInListeners: true`,
> а отключают только при необходимости.

Если вы используете DI и хотите контролировать автоподключение built-in behaviors, используйте `EntityManagerConfig`:

```php
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\EntityManagerConfig;

$em = new EntityManager(
    connection: $conn,
    config: new EntityManagerConfig(
        enableBuiltInListeners: true,
        // можно передать свой BuiltInListenersRegistryInterface
        builtInListenersRegistry: null,
    ),
);
```

Чтобы отключить автоподключение built-in behaviors:

```php
$em = new EntityManager(
    connection: $conn,
    config: new EntityManagerConfig(enableBuiltInListeners: false),
);
```
