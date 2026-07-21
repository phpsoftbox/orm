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

## Mongo Changelog Driver

Для хранения ORM changelog в Mongo можно использовать `PhpSoftBox\Orm\ChangeLog\Driver\MongoEntityChangeLogger`.

Драйвер ожидает mongo-manager с методом `collection(string $collection, string $connection): object`, а также доступный `ext-mongodb`.
