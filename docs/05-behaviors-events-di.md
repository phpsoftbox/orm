# Behaviors, события и DI

## События ORM

EntityManager вызывает события во время `flush()`:

- `OnCreate` / `AfterCreate`
- `OnUpdate` / `AfterUpdate`
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
- чтение по умолчанию скрывает удалённые записи

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
