# EntityManagerRegistry и multiple connections

## Задача

В приложениях часто нужно работать не с одним, а с несколькими DB connection:

- `default`
- `tenant`
- `analytics`
- и т.д.

Для этого в ORM есть registry-контракт, который выдаёт `EntityManagerInterface` для нужного connection.

## Контракты и реализации

- `PhpSoftBox\Orm\Contracts\EntityManagerRegistryInterface`
  - `runtimeRegistry(): EntityRuntimeRegistryInterface`
  - `default(bool $write = true): EntityManagerInterface`
  - `forConnection(string $connectionName, bool $write = true): EntityManagerInterface`
- `PhpSoftBox\Orm\ConnectionEntityManagerRegistry`
  - базовая реализация поверх `ConnectionManagerInterface`
- `PhpSoftBox\Orm\Contracts\EntityAwareEntityManagerRegistryInterface`
  - `connectionNameForEntity(string $entityClass): ?string`
  - `forEntity(string $entityClass, bool $write = true): EntityManagerInterface`

Маршрутизация entity и создание manager разделены намеренно. Обёртки registry, которым нужен собственный кеш
(например tenant-aware registry), получают имя connection через `connectionNameForEntity()`, а manager берут через
свой `forConnection()`. Это не даёт route binding создать второй `UnitOfWork` для той же entity.

## Базовая DI-настройка

```php
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Orm\ConnectionEntityManagerRegistry;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerRegistryInterface;
use PhpSoftBox\Orm\Contracts\EntityRuntimeRegistryInterface;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\UnitOfWork\EntityRuntimeRegistry;
use Psr\Container\ContainerInterface;

use function DI\factory;

return [
    EntityRuntimeRegistryInterface::class => factory(
        static fn (): EntityRuntimeRegistryInterface => new EntityRuntimeRegistry(),
    ),

    EntityManagerRegistryInterface::class => factory(
        static function (ContainerInterface $container): EntityManagerRegistryInterface {
            return new ConnectionEntityManagerRegistry(
                connections: $container->get(ConnectionManagerInterface::class),
                metadata: new AttributeMetadataProvider(),
                mapper: $container->get(AutoEntityMapper::class),
                defaultConnectionName: 'default',
                runtimeRegistry: $container->get(EntityRuntimeRegistryInterface::class),
            );
        },
    ),

    EntityManagerInterface::class => factory(
        static fn (ContainerInterface $container): EntityManagerInterface
            => $container->get(EntityManagerRegistryInterface::class)->default(),
    ),

    'orm.em.tenant' => factory(
        static fn (ContainerInterface $container): EntityManagerInterface
            => $container->get(EntityManagerRegistryInterface::class)->forConnection('tenant'),
    ),
];
```

## Общий runtime registry

У каждого `EntityManager` остаётся собственный `UnitOfWork` и собственный identity index. При этом managers,
созданные одним `ConnectionEntityManagerRegistry`, публикуют runtime-state своих entity в общий weak registry.
Поэтому одинаковые class/id допустимы одновременно в разных connection, а состояние relation определяется по
конкретному экземпляру объекта.

Runtime registry не хранит сильных ссылок на entity и не подключается к БД. Его следует регистрировать как singleton
процесса и передавать во все `ConnectionEntityManagerRegistry`/`ConnectionEntityManagerFactory`, если их несколько.
Это позволяет инфраструктурным потребителям, например Resource serializer, работать с entity разных connections и
tenant-контекстов без привязки к одному `UnitOfWork`.

## Рекомендация по использованию в коде

Не полагайтесь на то, что повторные вызовы registry вернут один и тот же instance.
Для одной операции берите manager в локальную переменную и используйте её:

```php
$em = $this->entityManagers->forConnection('tenant');
$em->persist($user);
$em->flush();
$em->pivot($user, 'roles')->sync([$roleId]);
```

## App-layer специализация (рекомендуется)

Чтобы не тянуть `->forConnection('tenant')`/`->tenant()` в бизнес-код, сделайте app-layer интерфейс:

```php
interface TenantEntityManagerInterface extends EntityManagerInterface
{
}
```

И адаптер, который делегирует вызовы в registry с tenant connection.
Тогда сервисы/репозитории инжектят только `TenantEntityManagerInterface`.

Плюсы:

- бизнес-код не знает про alias connection;
- точка переключения connection одна (DI binding);
- удобно переопределять в multi-tenant runtime без изменений прикладного кода.

## MultiTenant

В multi-tenant сценарии ответственность компонента tenancy:

- определить активный tenant;
- переключить runtime connection/DSN;
- вернуть корректный manager через app-layer binding.

ORM registry остаётся универсальным и не зависит от tenancy-логики.
