# Pivot Entity

Эта глава описывает поддержку **pivot entity** для связей many-to-many, когда pivot-таблица содержит дополнительные поля.

`#[BelongsToMany]` может:

- загрузить связанные сущности (например `Role`),
- загрузить строки pivot-таблицы,
- гидрировать pivot-сущность (например `UserRole`) и сохранить её в коллекции связи owner'а.

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
    )]
    public EntityCollection $roles;

    public function id(): int|null
    {
        return $this->id;
    }
}
```

## Как загружать pivot данные

Pivot относится к паре owner + related, поэтому хранится не в related-сущности, а в коллекции связи owner'а:
`EntityCollection::pivot($related)`.

```php
$em->load($user, 'roles');

foreach ($user->roles as $role) {
    /** @var UserRole|null $pivot */
    $pivot   = $user->roles->pivot($role);
    $created = $pivot?->createdDatetime;
}
```

Одна и та же managed-сущность `Role` может входить в связи разных пользователей (IdentityMap), и у каждого
пользователя будет свой pivot:

```php
$em->load([$anton, $maria], 'roles');

$anton->roles->pivot($adminRole); // строка user_roles для Anton
$maria->roles->pivot($adminRole); // строка user_roles для Maria
```

`pivot()` возвращает `null`, если элемента нет в этой коллекции или у связи не задан `pivotEntity`.
Pivot доступен только у коллекции, которую записала ORM (`load()`/`with()`); производные коллекции
(`filter()`, `map()` и т.п.) pivot не переносят.

## Pivot helpers (attach/detach/sync)

Для изменения pivot-таблицы используйте API:

- `$em->pivot($user, 'roles')->attach($roleId, $pivotData = [])`
- `$em->pivot($user, 'roles')->detach($roleId)`
- `$em->pivot($user, 'roles')->sync($roleIds)`
- `$em->pivot($user, 'roles')->syncWithPivotData($map, updatePivot: bool)`

Подробное описание `syncWithPivotData` (pivotData + updatePivot) находится в главе `Relations`.
