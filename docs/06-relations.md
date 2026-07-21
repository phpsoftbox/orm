# Relations (связи)

На текущем этапе ORM поддерживает декларативные связи через атрибуты и ручную (или batch) подгрузку через `EntityManager::load()`.

## Базовая идея

- Связи описываются в сущности атрибутами (`#[BelongsTo]`, `#[ManyToOne]`, `#[HasOne]`, `#[HasMany]`, `#[BelongsToMany]`, `#[HasManyThrough]`).
- Подгрузка выполняется методом `EntityManager::load($entityOrList, $relations)`.
- Можно передавать nested paths: `comments.author`.
- Для eager loading ORM использует batch-загрузку через `BulkEntityRepositoryInterface`.
  Если репозиторий целевой сущности не реализует `BulkEntityRepositoryInterface`,
  ORM автоматически использует `GenericEntityRepository` в режиме batch-гидрации
  (read-only, только для загрузки связей).

> Важно: сейчас подгрузка работает не через JOIN'ы, а через отдельные запросы (batch), чтобы не раздувать SQL и не усложнять гидрацию.

## Состояние загрузки relation

Значение relation и факт её загрузки — разные состояния. Entity при этом остаётся обычным объектом и не реализует
служебные интерфейсы ORM:

```php
final class Post implements EntityInterface
{
    #[HasMany(targetEntity: Comment::class, foreignKey: 'post_id')]
    public ?EntityCollection $comments = null;
}
```

`UnitOfWork` хранит lifecycle, snapshot и загруженные relations во внешнем `EntityHeap`. Каждому управляемому
экземпляру соответствует `EntityNode`; значение relation остаётся только в свойстве entity. Маркер `LoadedRelation`
также предусматривает completeness и fingerprint для будущих constrained eager loads.

Проверить состояние можно через Unit of Work:

```php
$em->unitOfWork()->isRelationLoaded($post, 'comments');
```

Инварианты:

- незапрошенная relation имеет состояние `false`;
- загруженная singular relation без результата содержит `null` и имеет состояние `true`;
- загруженная plural relation без результатов содержит пустую `EntityCollection` и имеет состояние `true`;
- состояние отмечается только после успешной записи relation;
- `refresh()` сбрасывает состояние relations;
- изменения pivot-таблицы инвалидируют состояние соответствующей relation.

Способы загрузки:

```php
$em->load($posts, ['comments']);        // загрузить/перезаписать
$em->loadMissing($posts, ['comments']); // не трогать уже загруженные
$em->reload($posts, ['comments']);      // явно перезагрузить
```

Результаты `find()`, query builder и batch-гидрации возвращают один managed instance для одинаковой пары
`(entity class, id)`. Второй пользовательский instance с уже управляемой identity считается ошибкой. Heap использует
слабые ссылки и не продлевает жизнь entity после завершения работы с ней.

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

## Фиксированные фильтры связи (Relation Scope)

Если связь всегда должна загружаться с дополнительными условиями, можно повесить scope на свойство связи.
Это не глобальный scope сущности: фильтр применяется только при `EntityManager::load()` / `with()` для конкретной связи.

Scope — обычный класс:

```php
use PhpSoftBox\Orm\Relation\Scope\RelationScopeInterface;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeQuery;

final class ActiveCommentsScope implements RelationScopeInterface
{
    public function apply(RelationScopeQuery $query): void
    {
        $query
            ->where('status = :comment_status', ['comment_status' => 'active'])
            ->orderBy('id', 'DESC');
    }
}
```

Пример на `HasMany`:

```php
#[Entity(table: 'posts')]
final class Post
{
    #[Id]
    #[Column(type: 'int')]
    public int $id;

    #[HasMany(targetEntity: Comment::class, foreignKey: 'post_id', localKey: 'id')]
    #[RelationScope(ActiveCommentsScope::class)]
    public EntityCollection $activeComments;
}
```

Для `BelongsToMany` можно отдельно фильтровать target-таблицу и pivot-таблицу:

```php
#[BelongsToMany(
    targetEntity: Role::class,
    pivotTable: 'user_roles',
    foreignPivotKey: 'user_id',
    relatedPivotKey: 'role_id',
)]
#[RelationScope(ActiveRolesScope::class)]
#[PivotScope(NonExpiredUserRolesScope::class)]
public EntityCollection $roles;
```

Для `HasManyThrough` аналогично доступен `#[ThroughScope(...)]`, который применяется к промежуточной таблице.

Поддерживаемые атрибуты:
- `#[RelationScope(...)]` — фильтр целевой таблицы связи.
- `#[PivotScope(...)]` — фильтр pivot-таблицы для `BelongsToMany`.
- `#[ThroughScope(...)]` — фильтр промежуточной таблицы для `HasManyThrough`.

Атрибуты можно повторять, scopes применяются в порядке объявления:

```php
#[RelationScope(ActiveRolesScope::class)]
#[RelationScope(VisibleRolesScope::class)]
public EntityCollection $roles;
```

По умолчанию ORM создает scope-класс напрямую через `new ScopeClass()`.
Если scope должен резолвиться из контейнера, передайте свой `RelationScopeResolverInterface`
в `EntityManager`, `ConnectionEntityManagerFactory` или `ConnectionEntityManagerRegistry`.

## Счётчики и агрегаты по связям

Для вывода вычисленных значений по связи используйте `withCount()`, `withExists()` и aggregate helpers.
Эти методы добавляют extra-колонки в результат запроса и не мутируют Entity.

```php
$posts = $em->queryFor(Post::class)
    ->withCount('comments')
    ->withExists('comments')
    ->withSum('comments', 'likes')
    ->fetchEntityResults();
```

Для `withCount('comments')` extra-колонка будет называться `comments_count`.
Для `withExists('comments')` — `comments_exists`.
Для `withSum('comments', 'likes')` — `comments_likes_sum`.

```php
$result = $posts[0];

$result->entity; // Post
$result->extra('comments_count');
$result->comments_count; // доступно для Resource::whenCounted()
```

Если нужен свой alias:

```php
$posts = $em->queryFor(Post::class)
    ->withCount('comments', alias: 'total_comments')
    ->fetchEntityResults();
```

Также поддерживается синтаксис:

```php
$posts = $em->queryFor(Post::class)
    ->withCount('comments as total_comments')
    ->fetchEntityResults();
```

Доступные helpers:
- `withCount($relation)`
- `withExists($relation)`
- `withAggregate($relation, $function, $column)`
- `withSum($relation, $column)`
- `withAvg($relation, $column)`
- `withMin($relation, $column)`
- `withMax($relation, $column)`

`with*` helpers учитывают `#[RelationScope]`, `#[PivotScope]` и `#[ThroughScope]`.
Динамические условия можно добавить callback'ом:

```php
$posts = $em->queryFor(Post::class)
    ->withCount('comments', callback: static function (OrmRelationQueryBuilder $query): void {
        $query->where($query->qualify('status') . ' = :status', ['status' => 'published']);
    })
    ->fetchEntityResults();
```

> Важно: если вызвать `fetchEntities()`, extra-колонки будут отброшены при гидрации сущностей.
> Для передачи счётчиков в Resource используйте `fetchEntityResults()` или `paginateEntityResults()`.

### MorphTo

Для `MorphTo` поддержаны только `withExists()` и `withCount()`.

```php
$logs = $em->queryFor(ActivityLog::class)
    ->withExists('subject')
    ->withCount('subject')
    ->fetchEntityResults();
```

`withCount('subject')` возвращает `0|1`, потому что `MorphTo` указывает на одну сущность.
`withSum/withAvg/withMin/withMax` для `MorphTo` не поддержаны: разные target-таблицы могут иметь разные колонки и разный смысл данных.

## Фильтрация по связям (whereHas)

`OrmSelectQueryBuilder::whereHas()` позволяет фильтровать сущности по наличию связанных записей.
Фильтр компилируется в коррелированный `EXISTS`, поэтому to-many relation не размножает строки корневой сущности
и не искажает pagination/count.

Поддерживаемые типы:
- `belongs_to_many`
- `has_many`
- `has_one`
- `many_to_one`
- `has_many_through`
- `morph_many`
- `morph_to`

### BelongsToMany

```php
$users = $em->queryFor(User::class)
    ->from('users u')
    ->whereHas('roles', static function (OrmRelationQueryBuilder $q): void {
        $q->where($q->qualify('role_id', true) . ' = :role_id', ['role_id' => 1]);
    })
    ->fetchEntities();
```

### HasManyThrough

```php
$companies = $em->queryFor(Company::class)
    ->from('companies c')
    ->whereHas('workItems', static function (OrmRelationQueryBuilder $q): void {
        $q->where($q->qualify('title') . ' LIKE :q', ['q' => '%alpha%']);
    })
    ->fetchEntities();
```

### MorphMany

```php
$posts = $em->queryFor(Post::class)
    ->from('posts p')
    ->whereHas('comments', static function (OrmRelationQueryBuilder $q): void {
        $q->where($q->qualify('body') . ' LIKE :q', ['q' => '%hello%']);
    })
    ->fetchEntities();
```

### MorphTo

```php
$logs = $em->queryFor(ActivityLog::class)
    ->from('activity_logs l')
    ->whereHas('subject', static function (OrmRelationQueryBuilder $q): void {
        $q->where($q->qualify('title') . ' = :title', ['title' => 'Hello']);
    })
    ->fetchEntities();
```

### Вложенные paths

Путь relation задаётся в том же dotted-формате, что и eager loading:

```php
$cells = $em->queryFor(Cell::class)
    ->whereHas(
        'row.place.zone.schemes',
        static fn (OrmRelationQueryBuilder $query) => $query
            ->whereProperty('scheme', '=', FulfillmentScheme::Fbs),
    )
    ->fetchEntities();
```

На каждом сегменте применяются relation scopes и soft-delete scope. Nullable промежуточная relation просто
не удовлетворяет `EXISTS`.

Дополнительные варианты:

```php
$query->orWhereHas('owner.company');
$query->whereDoesntHave('comments.author');
$query->orWhereDoesntHave('comments.author');
$query->whereRelation('comments.author', 'status', '=', AuthorStatus::Active);
```

`whereProperty()` и `whereRelation()` принимают property или имя колонки. ORM преобразует property в колонку
через метаданные; backed enum автоматически преобразуется в его scalar value.

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
