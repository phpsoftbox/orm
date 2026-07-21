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

## Behaviors

Behaviors — это «глобальные» правила ORM.

Сейчас реализовано:

### SoftDelete

Атрибут на сущности:

```php
#[SoftDelete(entityField: 'deletedDatetime', column: 'deleted_datetime')]
```

- `delete()` делает UPDATE `deleted_datetime` вместо физического DELETE
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

По умолчанию `EntityManager` регистрирует встроенные behaviors (например Sluggable) через `DefaultEventDispatcher`.

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
