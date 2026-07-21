# Репозитории и EntityManager

## Репозитории

Репозиторий отвечает за:
- построение SQL (через QueryBuilder)
- гидрацию сущностей
- сохранение/удаление сущностей

На текущем этапе есть базовые абстракции:
- `AbstractRepository`
- `AbstractEntityRepository`

### BulkEntityRepositoryInterface (batch-загрузка)

`BulkEntityRepositoryInterface` нужен для **eager loading связей** (метод `EntityManager::load()`), чтобы избежать N+1.

Идея проста:

- EntityManager собирает список ключей (`IN (...)`) для всех сущностей
- делает **один запрос** к БД
- а затем должен корректно превратить строки в объекты

Поэтому для загрузки связей репозиторий целевой сущности должен поддерживать batch-операции:

- `findManyByColumn()` — загрузка пачки сущностей по произвольной колонке
- `findManyByLookup()` — загрузка пачки сущностей по общей `LookupSpec`
- `hydrateManyRows()` — гидрация пачки строк (полезно для более сложных стратегий загрузки)

На практике это обычно «просто работает», если ваш репозиторий наследуется от `AbstractEntityRepository`.

### Database warmup

`GenericEntityRepository` умеет использовать lifecycle-scoped warmup API из `Database`,
если текущее подключение поддерживает `WarmupAwareConnectionInterface`.
Это не включает глобальное кеширование запросов: репозиторий отдаёт keyed-read в Database,
а Database сама проверяет прогретую строку по идентификатору `connection + table + column/value`
или дозагружает miss-значения. Для scoped lookup используется тот же `LookupSpec`, что
в validator/database warmup: таблица, lookup-column, fixed criteria и warmup key описаны
одним объектом.

Типичный сценарий:

1. Validator проверил массив идентификаторов через `ExistsValidation::all()->warmup()`.
2. `DatabaseValidationAdapter` одним запросом прогрел строки через `Connection::warmup()`.
3. Позже `GenericEntityRepository::find()`, `findManyByColumn()` или `findManyByLookup()`
   по тем же ключам может взять прогретые строки или дозагрузить только отсутствующие
   значения.

Для lookup по primary key `GenericEntityRepository` использует `manyUnique()` / `one()`,
где один lookup key обязан соответствовать максимум одной строке. Для lookup по
остальным колонкам используется `manyGrouped()`: Database warmup хранит `list<row>` на
ключ и не теряет one-to-many результаты.

Если подключение не поддерживает warmup API, репозиторий работает как раньше и сразу
выполняет обычный запрос в БД.

Для default-чтения soft-delete сущностей репозиторий не использует warmup по одному `id`,
потому что такой ключ не описывает условие `deleted_at IS NULL`. В этом случае выполняется
обычный scoped SQL-запрос. `findWithDeleted()` и сущности без soft-delete могут использовать
warmup по идентификатору.

## EntityManager

`EntityManager` хранит:
- подключение к БД (ConnectionInterface)
- UnitOfWork (tracking + dirty-checking)
- реестр репозиториев

### Зачем EntityManager::find() и findWithDeleted()

`EntityManager::find()` — это **входная точка с поддержкой 1st-level cache**.
Для поиска без soft-delete scope используйте такой же UoW-aware метод:

```php
$post = $em->findWithDeleted(Post::class, $id);
```

Оба метода сначала проверяют IdentityMap и после гидрации возвращают канонический
managed-экземпляр. Поэтому инфраструктурный код (например, route entity binding)
не должен вызывать `repository()->findWithDeleted()` напрямую.

Если в `EntityManager` используется `UnitOfWork`, то он включает **IdentityMap**:
- повторные запросы одной и той же сущности по id вернут **тот же объект**
- второй запрос может не обращаться к репозиторию/БД (экономия запросов)

Это важно для консистентности в рамках одного "юнита работы":
- вы гарантированно работаете с одним объектом сущности
- изменения, сделанные в объекте (до flush), видны везде, где вы получили эту же сущность

#### Пример

```php
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\UnitOfWork\UnitOfWork;

$em = new EntityManager(
    connection: $connection,
    unitOfWork: new UnitOfWork(),
);

$userA = $em->find(User::class, $id);
$userB = $em->find(User::class, $id);

// Один и тот же instance (identity map)
assert($userA === $userB);

// Мутация видна везде
$userA->name = 'John';
assert($userB->name === 'John');
```

#### Чем отличается от $em->repository()->find()

`$em->repository(User::class)->find($id)` всегда вызывает репозиторий.

`$em->find(User::class, $id)` сначала пытается вернуть сущность из IdentityMap (если она уже загружена в текущем UnitOfWork).

## EntityManager::queryFor

`EntityManager::queryFor()` возвращает ORM-ориентированный query builder (`OrmSelectQueryBuilder`).

Он удобен, когда нужно:
- собрать произвольный SQL (через fluent API QueryBuilder)
- при этом иметь доступ к soft delete scope
- и при необходимости загрузить связи (eager loading)

### Soft delete scope

По умолчанию builder исключает soft-deleted записи (как и репозитории).

Есть явные методы:

```php
$qb = $em->queryFor(User::class);

// включает удалённые
$qb->withDeleted();

// только удалённые
$qb->onlyDeleted();
```

### Eager loading через with()

`with()` задаёт связи, которые будут автоматически подгружены при `fetchEntities()` или `paginateEntities()`:

```php
$users = $em
    ->queryFor(User::class)
    ->with(['roles'])
    ->orderBy('id', 'DESC')
    ->fetchEntities();
```

Для пагинации:

```php
$pagination = $em
    ->queryFor(User::class)
    ->with(['roles'])
    ->paginateEntities($page, $perPage);
```

Важно: `fetchAll()` и `paginate()` по-прежнему возвращают массивы строк (как обычный QueryBuilder).
Если нужны сущности, используйте `fetchEntities()` / `paginateEntities()`.

## EntityManager::bulk

`EntityManager::bulk()` возвращает set-based writer для массовых операций без загрузки сущностей.

Это отдельный write-path:
- выполняет `UPDATE ... WHERE ... IN (...)` или `DELETE ... WHERE ... IN (...)`
- не вызывает entity-события `OnDelete`, `OnUpdate`, `OnRestore` для каждой записи
- не строит per-entity changelog, потому что сущности не загружаются
- после успешной операции очищает UnitOfWork, чтобы уже загруженные объекты не остались stale
- не запускается, если в UnitOfWork уже есть запланированные операции; сначала вызовите `flush()`

Пример soft delete по id:

```php
$result = $em
    ->bulk(Product::class)
    ->ids([1, 2, 3])
    ->remove();
```

Если сущность помечена `#[SoftDelete]`, `remove()` выполнит:

```sql
UPDATE products
SET deleted_datetime = ...
WHERE id IN (...)
```

Если `#[SoftDelete]` нет, `remove()` выполнит физический `DELETE`.

Для физического удаления есть явный метод:

```php
$em
    ->bulk(Product::class)
    ->ids([1, 2, 3])
    ->forceRemove();
```

Для восстановления soft-deleted записей:

```php
$em
    ->bulk(Product::class)
    ->ids([1, 2, 3])
    ->restore();
```

Для scoped lookup используйте `LookupSpec`:

```php
use PhpSoftBox\DatabaseLookup\LookupSpec;

$lookup = LookupSpec::forTable('shipment_products')
    ->lookupColumn('product_id')
    ->values($productIds)
    ->where('shipment_id', $shipmentId);

$em
    ->bulk(ShipmentProduct::class)
    ->lookup($lookup)
    ->update(['status' => 'accepted']);
```

`update()` принимает имена свойств сущности или физические имена колонок. Если у сущности есть
updatable-колонка `updatedDatetime` / `updated_datetime`, bulk update выставит её тем же
механизмом, что и обычный `TimestampsListener`.

Результат операции:

```php
$result->action;          // BulkWriteAction
$result->requestedValues; // количество уникальных lookup values
$result->affectedRows;    // rowCount(), который вернула БД
$result->lookupValues;    // уникальные lookup values
```

Для bulk-write есть отдельные события:
- `OnBulkUpdate` / `AfterBulkUpdate`
- `OnBulkRemove` / `AfterBulkRemove`
- `OnBulkForceRemove` / `AfterBulkForceRemove`
- `OnBulkRestore` / `AfterBulkRestore`

События содержат `entityClass`, `LookupSpec`, `BulkWriteAction`, `MutableBulkWriteState`.
`After*` дополнительно содержит `BulkWriteResult`.

## Авто-резолв репозитория

Если репозиторий не зарегистрирован вручную (`registerRepository()`), `EntityManager` пытается автоматически создать репозиторий.

По умолчанию используется цепочка стратегий (через `RepositoryResolverInterface`):
1) `#[Entity(repository: ...)]`
2) Поиск в `defaultRepositoryNamespaces()` → `{Ns}\\{Entity}Repository`
3) Поиск по соглашению: `{EntityNamespace}\\{repositoryNamespace}\\{Entity}Repository`
4) Fallback на `GenericEntityRepository`

### Пример настройки через DI (defaultRepositoryNamespaces)

Если вы хотите хранить репозитории в отдельном namespace (например: `App\Repository`),
можно сконфигурировать `DefaultRepositoryResolver` и передать его в `RepositoryClassFactory`:

```php
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Repository\DefaultRepositoryResolver;
use PhpSoftBox\Orm\Repository\RepositoryClassFactory;

DefaultRepositoryResolver::class => static function (Container $c) {
    return new DefaultRepositoryResolver([
        'App\\Repository',
    ]);
},

RepositoryClassFactory::class => static function (Container $c) {
    return new RepositoryClassFactory(
        metadata: $c->get(MetadataProviderInterface::class),
        resolver: $c->get(DefaultRepositoryResolver::class),
    );
}
```

В этом случае:

- `App\Entity\User` → `App\Repository\UserRepository`

Если репозиторий не найден (и fallback не подходит под ваш сценарий), будет выброшено `RepositoryNotRegisteredException`.

## Multiple connections и registry

Отдельный гайд по работе с несколькими connection и registry:
- [EntityManagerRegistry и multiple connections](08-entity-manager-registry.md)
