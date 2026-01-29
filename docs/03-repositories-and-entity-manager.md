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
- `hydrateManyRows()` — гидрация пачки строк (полезно для более сложных стратегий загрузки)

На практике это обычно «просто работает», если ваш репозиторий наследуется от `AbstractEntityRepository`.

## EntityManager

`EntityManager` хранит:
- подключение к БД (ConnectionInterface)
- UnitOfWork (tracking + dirty-checking)
- реестр репозиториев

### Зачем EntityManager::find()

`EntityManager::find()` — это **входная точка с поддержкой 1st-level cache**.

Если в `EntityManager` используется `AdvancedUnitOfWork`, то он включает **IdentityMap**:
- повторные запросы одной и той же сущности по id вернут **тот же объект**
- второй запрос может не обращаться к репозиторию/БД (экономия запросов)

Это важно для консистентности в рамках одного "юнита работы":
- вы гарантированно работаете с одним объектом сущности
- изменения, сделанные в объекте (до flush), видны везде, где вы получили эту же сущность

#### Пример

```php
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\IdentityMap\WeakIdentityMap;
use PhpSoftBox\Orm\UnitOfWork\AdvancedUnitOfWork;

$em = new EntityManager(
    connection: $connection,
    unitOfWork: new AdvancedUnitOfWork(new WeakIdentityMap()),
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
