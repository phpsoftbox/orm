# Relations (связи)

На текущем этапе ORM поддерживает декларативные связи через атрибуты и ручную (или batch) подгрузку через `EntityManager::load()`.

## Базовая идея

- Связи описываются в сущности атрибутами (`#[BelongsTo]`, `#[ManyToOne]`, `#[HasOne]`, `#[HasMany]`, `#[BelongsToMany]`, `#[HasManyThrough]`).
- Подгрузка выполняется методом `EntityManager::load($entityOrList, $relations)`.
- Можно передавать nested paths: `comments.author`.

> Важно: сейчас подгрузка работает не через JOIN'ы, а через отдельные запросы (batch), чтобы не раздувать SQL и не усложнять гидрацию.

## BelongsTo (alias для ManyToOne)

`#[BelongsTo]` — это синтаксический сахар над `#[ManyToOne]`.

- По метаданным это та же связь `many_to_one`
- `referencedColumn` по умолчанию равен `id`
- Нельзя ставить `#[BelongsTo]` и `#[ManyToOne]` одновременно на одно свойство (будет исключение)

Пример:

```php
#[Entity(table: 'posts')]
final class Post
{
    #[Column(name: 'author_id', type: 'int')]
    public int $authorId;

    #[BelongsTo(targetEntity: Author::class, joinColumn: 'authorId')]
    public ?Author $author = null;
}
```

Подгрузка:

```php
$em->load($post, 'author');
```

## ManyToOne

(Низкоуровневый эквивалент `BelongsTo`.)

Пример:

```php
#[Entity(table: 'posts')]
final class Post
{
    #[Column(name: 'author_id', type: 'int')]
    public int $authorId;

    #[ManyToOne(targetEntity: Author::class, joinColumn: 'authorId', referencedColumn: 'id')]
    public ?Author $author = null;
}
```

Подгрузка:

```php
$em->load($post, 'author');
// $post->author заполнен объектом Author или null
```

## HasOne

```php
#[Entity(table: 'users')]
final class User
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[HasOne(targetEntity: Profile::class, foreignKey: 'user_id', localKey: 'id')]
    public ?Profile $profile = null;
}
```

```php
$em->load($user, 'profile');
```

## HasMany

```php
#[Entity(table: 'posts')]
final class Post
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[HasMany(targetEntity: Comment::class, foreignKey: 'post_id', localKey: 'id')]
    public EntityCollection $comments;
}
```

```php
$em->load($post, 'comments');
```

## BelongsToMany

Связь многие-ко-многим через pivot-таблицу.

> Pivot-таблица (junction table) — это таблица, которая хранит пары идентификаторов (`user_id`, `role_id`) и, при необходимости,
> дополнительные данные связи (например `created_datetime`, `granted_by_user_id`, `expires_datetime`).

### Когда нужно указывать pivotTable вручную

Указывать `pivotTable`/ключи **обязательно**, если:

1) **Название pivot-таблицы не соответствует конвенции**.
   - По умолчанию ORM пытается вывести `pivotTable` как `<ownerSingular>_<relatedPlural>`.
   - Если у вас в БД таблица называется иначе (`user_roles`, `users_to_roles`, `acl_user_role` и т.п.) — задайте `pivotTable` явно.

2) **Названия колонок в pivot-таблице не соответствуют конвенции**.
   - По умолчанию ключи выводятся как `<singular(table)>_id`.
   - Если у вас `uid`/`role_uuid`/`member_id` — задайте `foreignPivotKey/relatedPivotKey` явно.

3) **Одна и та же пара сущностей связана несколькими pivot-таблицами**.
   - Например, `users` <-> `roles` (обычные роли) и отдельно `users` <-> `roles` (временные роли) с другой таблицей.

4) **Self-referencing связь**.
   - Например, `users` <-> `users` (`user_id`, `friend_user_id`). Конвенция не сможет угадать второй ключ.

### Пример 1: таблица по конвенции (можно не указывать pivotTable)

Если у вас в БД pivot-таблица названа по конвенции и ключи стандартные, можно писать только `targetEntity`:

```php
#[Entity(table: 'users')]
final class User
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[BelongsToMany(targetEntity: Role::class)]
    public EntityCollection $roles;
}
```

При таблицах `users` и `roles` ORM выведет:
- `pivotTable`: `user_roles`
- `foreignPivotKey`: `user_id`
- `relatedPivotKey`: `role_id`

### Пример 2: нестандартная pivot-таблица (нужно указать вручную)

Частый реальный вариант — таблица называется `user_roles` (не `roles_users`):

```php
#[Entity(table: 'users')]
final class User
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[BelongsToMany(
        targetEntity: Role::class,
        pivotTable: 'user_roles',
        foreignPivotKey: 'user_id',
        relatedPivotKey: 'role_id',
    )]
    public EntityCollection $roles;
}
```

### Пример 3: pivot c UUID / нестандартными ключами

```php
#[Entity(table: 'users')]
final class User
{
    #[Id]
    #[Column(type: 'uuid')]
    public UuidInterface $id;

    #[BelongsToMany(
        targetEntity: Role::class,
        pivotTable: 'user_roles',
        foreignPivotKey: 'user_uuid',
        relatedPivotKey: 'role_uuid',
    )]
    public EntityCollection $roles;
}
```

### Пример 4: pivot с дополнительными колонками

ORM пока использует pivot-таблицу только как «связку» ID-шников для загрузки связей.
Если в pivot-таблице есть дополнительные поля (`created_datetime`, `expires_datetime` и т.п.),
то есть два рабочих подхода:

1) Вынести pivot-таблицу в отдельную сущность и работать с ней как с обычной таблицей.
2) Оставить `BelongsToMany` только для загрузки сущностей, а дополнительные данные читать отдельным запросом.

> В будущем можно расширить ORM, чтобы у `BelongsToMany` появился вариант «pivot Entity».

---

### Defaults (если не указывать pivotTable/ключи)

Если вы не задаёте `pivotTable`, `foreignPivotKey`, `relatedPivotKey`, ORM попробует вывести их по конвенции:

- `pivotTable` вычисляется как `<ownerSingular>_<relatedPlural>`:
  - `users` + `roles` -> `user_roles`
- `foreignPivotKey` вычисляется как `<singular(leftTable)>_id`:
  - `users` -> `user_id`
- `relatedPivotKey` вычисляется как `<singular(rightTable)>_id`:
  - `roles` -> `role_id`

> Важно: если у сущности явно задан `#[Entity(table: ...)]`, то используются именно эти имена таблиц.
> Если table не задан — он будет выведен из имени класса.

## Важный нюанс: pivot defaults зависят от стороны (owner)

Наша конвенция для `pivotTable` — **owner-first**:

- owner = сущность, **в которой объявлен** `#[BelongsToMany]`
- related = `targetEntity`

Поэтому:

- если связь объявлена в `User` (table `users`) как `targetEntity: Role::class` (table `roles`),
  то дефолт будет `user_roles`
- если ту же связь объявить в `Role` (table `roles`) как `targetEntity: User::class` (table `users`),
  то дефолт будет `role_users`

Это нормально: ORM не сортирует таблицы по алфавиту и не пытается «угадать» единственно правильное имя.
Если в вашем проекте pivot-таблица имеет строгое имя (`user_roles`), то **на второй стороне** связь лучше
объявлять с явным `pivotTable` (и ключами), чтобы обе стороны ссылались на одну и ту же таблицу.

### BelongsToMany на обеих сторонах

Связь many-to-many обычно описывают **на обеих сторонах** как `BelongsToMany`.

`HasMany` — это one-to-many (например `User -> Posts`) и к many-to-many не относится.

Пример (обе стороны используют одну pivot-таблицу `user_roles`):

```php
#[Entity(table: 'users')]
final class User
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[BelongsToMany(
        targetEntity: Role::class,
        pivotTable: 'user_roles',
        foreignPivotKey: 'user_id',
        relatedPivotKey: 'role_id',
    )]
    public EntityCollection $roles;
}

#[Entity(table: 'roles')]
final class Role
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[BelongsToMany(
        targetEntity: User::class,
        pivotTable: 'user_roles',
        foreignPivotKey: 'role_id',
        relatedPivotKey: 'user_id',
    )]
    public EntityCollection $users;
}
```

---

### Рекомендация: «главная» сторона генерирует pivot автоматически, обратная — указывает owner

Если вы используете конвенцию `user_roles` (owner-first), то удобно выбирать одну сторону как **главную** (owner) и:

- на главной стороне (`User`) не писать `pivotTable` вообще (и полагаться на auto-guess)
- на обратной стороне (`Role`) **явно** привязывать связь к owner, чтобы не получить `role_users`

С текущим API это означает: на обратной стороне вы просто явно задаёте `pivotTable` и ключи (как в примере ниже).

> Идея на будущее (предложение по API): добавить возможность указать owner не строкой, а классом.
> Например: `pivotOwner: User::class`.
> Тогда ORM сможет:
> - сгенерировать `pivotTable` так же, как на owner-стороне (`user_roles`)
> - автоматически «поменять местами» ключи для обратной стороны (`foreignPivotKey`/`relatedPivotKey`),
>   чтобы обе стороны ссылались на одну и ту же pivot-таблицу.
> 
> Важно: это НЕ означает, что ORM переименовывает колонки в БД.
> Колонки остаются такими, как в pivot-таблице (например `user_roles.user_id` и `user_roles.role_id`).
> «Переворот» означает только то, что:
> - на стороне `User -> roles` foreignPivotKey = `user_id`, relatedPivotKey = `role_id`
> - на стороне `Role -> users` foreignPivotKey = `role_id`, relatedPivotKey = `user_id`

#### Пример (рекомендуемый паттерн)

Главная сторона (owner-first defaults):

```php
#[Entity(table: 'users')]
final class User
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    // pivotTable будет выведен автоматически: user_roles
    #[BelongsToMany(targetEntity: Role::class)]
    public EntityCollection $roles;
}
```

Обратная сторона (явно привязана к той же pivot-таблице):

```php
#[Entity(table: 'roles')]
final class Role
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[BelongsToMany(
        targetEntity: User::class,
        pivotTable: 'user_roles',
        foreignPivotKey: 'role_id',
        relatedPivotKey: 'user_id',
    )]
    public EntityCollection $users;
}
```

---

### Идея на будущее: owner через Entity::class (без ручного pivotTable на обратной стороне)

Ниже — пример того, как мог бы выглядеть будущий API (названия ещё можно обсудить):

```php
#[Entity(table: 'roles')]
final class Role
{
    #[BelongsToMany(
        targetEntity: User::class,
        pivotOwner: User::class, // или pivotForeignEntity / pivotOwnerTable
    )]
    public EntityCollection $users;
}
```

Семантика:
- `pivotOwner` говорит ORM: «главная сторона — User»
- Тогда pivotTable будет сгенерирован как `user_roles`, даже если связь объявлена в `Role`.

> Это позволит сохранять один источник правды для имени pivot-таблицы и не дублировать строки.

---

### TODO: pivot entity (подробные примеры)

Сейчас `BelongsToMany` использует pivot-таблицу только как «связку» ID-шников.
Если в pivot есть дополнительные поля (например `created_datetime`, `expires_datetime`, `granted_by_user_id`),
то обычно хочется получить их как часть модели.

Ниже — примеры, как мы можем развить ORM.

#### Вариант A (рекомендуемый): pivot как отдельная сущность (явная модель)

Например, у нас есть:
- `users`
- `roles`
- `user_roles` с дополнительным полем `created_datetime`

Тогда делаем pivot entity:

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

Плюсы:
- максимально прозрачно
- можно делать CRUD по pivot как по обычной таблице
- можно навесить behaviors/typecasting/softdelete и т.д.

Минусы:
- это не «BelongsToMany с extras», а отдельная модель

#### Вариант B: BelongsToMany с pivotEntity (будущее расширение)

Потенциальный API (предложение):

```php
#[BelongsToMany(
    targetEntity: Role::class,
    pivotTable: 'user_roles',
    foreignPivotKey: 'user_id',
    relatedPivotKey: 'role_id',
    pivotEntity: UserRole::class,
)]
public EntityCollection $roles;
```

Идея:
- `$user->roles` возвращает `EntityCollection<Role>` как сейчас
- при этом `EntityManager::load()` дополнительно загружает pivot-строку `UserRole` и прикрепляет её
  либо как `$role->__pivot` (внутреннее поле), либо через отдельный accessor.

#### Доступ к pivot данным (пример будущего API)

Например:

```php
$em->load($user, 'roles');

foreach ($user->roles as $role) {
    // например, доступ к pivot-данным
    $created = $role->pivot()->createdDatetime;
}
```

#### Attach/Detach/Sync (будущий API)

Чтобы работать с pivot как с отношением, обычно нужны методы уровня repository/ORM:

```php
// Добавить роль пользователю
$userRepo->attach($user, 'roles', $roleId, ['created_datetime' => new DateTimeImmutable()]);

// Удалить
$userRepo->detach($user, 'roles', $roleId);

// Синхронизировать список ролей
$userRepo->sync($user, 'roles', [$roleId1, $roleId2]);
```

Это логично ложится на следующий этап ORM, когда появятся relation helpers.
