# PhpSoftBox ORM

ORM компонент для PhpSoftBox. Работает поверх `phpsoftbox/database`.

> Статус: beta

## Рекомендации по работе с ORM

Ниже — практики, которые мы используем в проекте и на которые опирается код:

1) **Entity — источник правды.** Репозитории возвращают сущности (`?Entity`, `EntityCollection`), а не массивы.
2) **`create()` возвращает Entity.** Проверки `id <= 0` не нужны — ORM кидает исключение, если id не назначен.
3) **Репозиторий без бизнес‑логики.** Правила, валидация и ошибки — в сервисах.
4) **Использовать relations вместо join, когда возможно.** `with()/whereHas()` предпочтительнее ручного join.
5) **Прямой доступ к `connection()` — только там, где ORM не покрывает кейс.**  
   Типовые случаи: bulk‑операции, pivot‑таблицы, служебные массовые апдейты.
6) **Входные данные должны быть нормализованы до репозитория.**  
   `RequestSchema`/DTO/сервис формируют payload; репозиторий только применяет его к сущности.
7) **Контроллеры возвращают Resource, а не массивы.**  
   `Resource::collection()` для списков, `new Resource($entity)` для единичных.
8) **Жизненный цикл — через `EntityManager`.**  
   `persist/flush` на сущности, не смешивать с ручным SQL без крайней нужды.
9) **Транзакции — в сервисах.**  
   Если операция затрагивает несколько репозиториев, управляет сервис.

## Оглавление

- [Quick Start](docs/01-quick-start.md)
- [Атрибуты и метаданные](docs/02-metadata-and-attributes.md)
- [Репозитории и EntityManager](docs/03-repositories-and-entity-manager.md)
- [TypeCasting](docs/04-typecasting.md)
- [Behaviors, события и DI](docs/05-behaviors-events-di.md)
- [Relations (связи)](docs/06-relations.md)
- [Pivot Entity](docs/07-pivot-entity.md)
- [EntityManagerRegistry и multiple connections](docs/08-entity-manager-registry.md)
- [Full-Text Search](docs/09-full-text-search.md)

## Quick Start

См. [docs/01-quick-start.md](docs/01-quick-start.md).

## Изменения поведения в 1.0

- Исключения `#[Hook]` и listeners, а также ошибки создания `#[EventListener]` пробрасываются из `flush()`
  (раньше молча игнорировались). Listener с зависимостями создаётся через `ContainerListenerResolver`
  ([события и DI](docs/05-behaviors-events-di.md)).
- `#[EventListener]` сущности получает только события своей сущности (включая bulk-события).
- Метод listener'а с `#[Listen]` не вызывается повторно по соглашению `on*`.
- Встроенные listeners больше не регистрируются в переданный `events`-dispatcher.
- Pivot для `BelongsToMany` с `pivotEntity` хранится в коллекции связи: `$user->roles->pivot($role)`;
  `HasPivotInterface`, `HasPivotTrait` и параметр `pivotAccessor` удалены ([Pivot Entity](docs/07-pivot-entity.md)).
- `#[GeneratedValue(strategy: 'uuid')]` генерирует UUID при INSERT; свойства читаются и пишутся через Reflection
  (поддерживаются `readonly` и непубличные свойства).
- `ConnectionEntityManagerRegistry`/`ConnectionEntityManagerFactory` принимают `events`, `listenerResolver`, `config`
  и создают managers так же, как `new EntityManager()` (metadata по умолчанию — с naming convention).
- `flush()` обновляет только изменённые колонки (плюс колонки слушателей `OnUpdate`).
- Bulk update приводит значения через DataCasting и выполняется в транзакции.
- `paginate*()` поддерживают `paginationContext()`/`usePaginator()` для path и query в ссылках.
- Ключи связей — имена колонок (имя свойства тоже допускается); pivot helpers учитывают `parentKey`;
  `with()` накапливает связи.

## Mongo Changelog Driver

Для хранения ORM changelog в Mongo можно использовать `PhpSoftBox\Orm\ChangeLog\Driver\MongoEntityChangeLogger`.

Драйвер ожидает mongo-manager с методом `collection(string $collection, string $connection): object`, а также доступный `ext-mongodb`.
