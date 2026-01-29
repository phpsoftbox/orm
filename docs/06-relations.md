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

### Pivot helpers (attach/detach/sync)

Для управления pivot-таблицей используйте pivot-менеджер:

```php
$em->pivot($user, 'roles')->attach(10);
$em->pivot($user, 'roles')->detach(10);
$em->pivot($user, 'roles')->sync([11, 12]);
```

#### syncWithPivotData (pivotData + updatePivot)

Если вам нужно записывать дополнительные поля pivot-таблицы, используйте `syncWithPivotData()`.

Сигнатура (упрощённо):

- `relatedIdToPivotData`: карта `<relatedId => pivotData>`
- `updatePivot`:
  - `false` (по умолчанию) — для **существующих** связей не обновляет pivot-данные
  - `true` — для существующих связей выполняет `UPDATE` по полям из `pivotData`

Пример:

```php
$em->pivot($user, 'roles')->syncWithPivotData([
    10 => ['created_datetime' => '2026-01-27T12:00:00+00:00'],
    11 => ['created_datetime' => '2026-01-27T12:05:00+00:00'],
]);

// Обновить pivot-поля для существующих связей:
$em->pivot($user, 'roles')->syncWithPivotData([
    10 => ['created_datetime' => '2026-01-27T13:00:00+00:00'],
    11 => ['created_datetime' => '2026-01-27T12:05:00+00:00'],
], updatePivot: true);
```

Правила работы `syncWithPivotData()`:

1) Связи, которых нет в списке — удаляются (DELETE)
2) Связи, которых нет в БД — добавляются (INSERT) с `pivotData`
3) Связи, которые уже есть:
   - при `updatePivot=false` pivot-данные не трогаются
   - при `updatePivot=true` выполняется UPDATE по полям из `pivotData` (если массив не пустой)

> Важно: pivot helpers пишут напрямую в БД (вне UnitOfWork).
> Если у вас уже загружены связи через `$em->load(...)`, то после изменения pivot может понадобиться повторный `$em->load(...)`.

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

Если в pivot-таблице есть дополнительные поля (`created_datetime`, `expires_datetime` и т.п.), есть два основных сценария:

1) **Нужны только данные связки (ID) + управление связями**
   - используйте pivot helpers: `$em->pivot($user, 'roles')->attach/detach/sync(...)`

2) **Нужны дополнительные поля pivot как часть модели**
   - используйте `pivotEntity` + accessor (`pivot()`), см. главу [Pivot Entity](07-pivot-entity.md)

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

## Как сейчас и как будет (примеры расширений)

Ниже — короткие примеры, чтобы было видно разницу: что уже есть, и что могло бы появиться, если будем расширять ORM.

### 1) Самоссылка (self reference) — уже можно, без новых типов

Это не новый тип relation. Это обычный `HasMany`/`ManyToOne`, просто `targetEntity` = текущий класс.

**Сейчас (и будет так же):**

```php
#[Entity(table: 'categories')]
final class Category
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[Column(name: 'parent_id', type: 'int', nullable: true)]
    public ?int $parentId = null;

    #[ManyToOne(targetEntity: Category::class, joinColumn: 'parentId', referencedColumn: 'id')]
    public ?Category $parent = null;

    #[HasMany(targetEntity: Category::class, foreignKey: 'parent_id', localKey: 'id')]
    public EntityCollection $children;
}
```

Загрузка:

```php
$em->load($category, ['parent', 'children']);
```

### 2) Polymorphic (Morph) — сейчас нет, «как будет»

Если захотим поддержать комментарии к разным сущностям (Post/Video/...), обычно делают поля:

- `commentable_type` (строка)
- `commentable_id` (int/uuid)

**Сейчас:** это придётся решать вручную на уровне приложения.

**Как будет (предложение):**

```php
#[Entity(table: 'comments')]
final class Comment
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[Column(name: 'commentable_type', type: 'string')]
    public string $commentableType;

    #[Column(name: 'commentable_id', type: 'int')]
    public int $commentableId;

    #[MorphTo(
        typeColumn: 'commentable_type',
        idColumn: 'commentable_id',
        map: [
            'post' => Post::class,
            'video' => Video::class,
        ],
    )]
    public object|null $commentable = null;
}

#[Entity(table: 'posts')]
final class Post
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[MorphMany(
        targetEntity: Comment::class,
        typeColumn: 'commentable_type',
        idColumn: 'commentable_id',
        typeValue: 'post',
    )]
    public EntityCollection $comments;
}
```

Ожидаемое поведение `load()`:

```php
// $comments — список Comment
$em->load($comments, 'commentable');

// ORM группирует Comment по commentableType и делает несколько batch-запросов:
// - один IN(...) по post
// - один IN(...) по video
// ...
```
