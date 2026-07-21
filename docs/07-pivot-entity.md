# Pivot Entity

Эта глава описывает поддержку **pivot entity** для связей many-to-many, когда pivot-таблица содержит дополнительные поля.

`#[BelongsToMany]` может:

- загрузить связанные сущности (например `Role`),
- загрузить строки pivot-таблицы,
- гидрировать pivot-сущность (например `UserRole`) и прикрепить к target entity через accessor.

## Термины

- **pivot-таблица** — таблица связи many-to-many, например `user_roles`.
- **pivot entity** — сущность, которая маппится на pivot-таблицу и содержит дополнительные поля.

Пример схемы:

- `users (id, ...)`
- `roles (id, ...)`
- `user_roles (id, user_id, role_id, created_datetime, expires_datetime, ...)`

## Важно: мы всегда используем реальные имена колонок в pivot-таблице

В ORM мы **не вводим** абстрактные имена вроде `owner_id/related_id`.

Вместо этого `BelongsToMany` работает с **реальными** названиями колонок в pivot-таблице:

- `foreignPivotKey` — колонка pivot, которая указывает на **текущую сущность** (ту, где объявлена связь)
- `relatedPivotKey` — колонка pivot, которая указывает на **targetEntity**

Пример (pivot-таблица `user_roles`):

- `User -> roles`:
  - `foreignPivotKey = user_id`
  - `relatedPivotKey = role_id`
- `Role -> users`:
  - `foreignPivotKey = role_id`
  - `relatedPivotKey = user_id`

## Пример: pivot как отдельная сущность

```php
#[Entity(table: 'user_roles')]
final class UserRole implements EntityInterface
{
    #[Id]
    #[Column(type: 'primary')]
    public int $id;

    #[Column(name: 'user_id', type: 'int')]
    public int $userId;

    #[Column(name: 'role_id', type: 'int')]
    public int $roleId;

    #[Column(name: 'created_datetime', type: 'datetime')]
    public DateTimeImmutable $createdDatetime;

    public function id(): int|null
    {
        return $this->id;
    }
}
```

## BelongsToMany + pivotEntity

```php
#[Entity(table: 'users')]
final class User implements EntityInterface
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[BelongsToMany(
        targetEntity: Role::class,
        pivotTable: 'user_roles',
        foreignPivotKey: 'user_id',
        relatedPivotKey: 'role_id',
        pivotEntity: UserRole::class,
        pivotAccessor: 'pivot',
    )]
    public EntityCollection $roles;

    public function id(): int|null
    {
        return $this->id;
    }
}
```

### Pivot accessor и IDE autocomplete

Чтобы сохранить автодополнение IDE, pivot лучше предоставлять **не через магические свойства**, а через метод.

Рекомендуемый вариант: `HasPivotInterface` + `HasPivotTrait`.

Пример использования в target entity:

```php
use PhpSoftBox\Orm\Relation\HasPivotInterface;
use PhpSoftBox\Orm\Relation\HasPivotTrait;

/**
 * @implements HasPivotInterface<UserRole>
 */
#[Entity(table: 'roles')]
final class Role implements EntityInterface, HasPivotInterface
{
    /**
     * @use HasPivotTrait<UserRole>
     */
    use HasPivotTrait;

    // ...остальные поля...
}
```

## Как загружать pivot данные

```php
$em->load($user, 'roles');

foreach ($user->roles as $role) {
    $created = $role->pivot()?->createdDatetime;
}
```

## Pivot helpers (attach/detach/sync)

Для изменения pivot-таблицы используйте API:

- `$em->pivot($user, 'roles')->attach($roleId, $pivotData = [])`
- `$em->pivot($user, 'roles')->detach($roleId)`
- `$em->pivot($user, 'roles')->sync($roleIds)`
- `$em->pivot($user, 'roles')->syncWithPivotData($map, updatePivot: bool)`

Подробное описание `syncWithPivotData` (pivotData + updatePivot) находится в главе `Relations`.

## Ограничение: IdentityMap и «pivot на сущности»

Если одна и та же сущность `Role` шарится между разными owner в рамках одного UnitOfWork (IdentityMap),
то pivot-на-сущности может перетираться.

На текущем этапе это ограничение принимаем как MVP:

- pivot корректен при использовании relation-коллекции в контексте одного owner (типичный сценарий)

В будущем можно улучшить:

- хранить pivot не в entity, а в relation-context коллекции
- или возвращать wrapper-объекты (`RoleWithPivot`)
