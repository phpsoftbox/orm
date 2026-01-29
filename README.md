# PhpSoftBox ORM

ORM компонент для PhpSoftBox. Работает поверх `phpsoftbox/database`.

> Статус: beta

## Roadmap / планы на будущее

Ниже — список задач, которые мы осознанно оставили на следующие итерации (в порядке примерного приоритета):

1) **Inflector contracts**
   - Сейчас ORM подтягивает пакет `phpsoftbox/inflector` как реальную зависимость.
   - В будущем нужно вынести интерфейсы в отдельный пакет (например `phpsoftbox/inflector-contracts`),
     чтобы можно было подключать любой инфлектор без установки всего `phpsoftbox/inflector`.

2) **BelongsToMany: единый pivotTable для обеих сторон (pivotOwner)**
   - Сейчас defaults зависят от стороны: `User->roles` -> `user_roles`, а `Role->users` -> `role_users`.
   - Хотим добавить параметр `pivotOwner: SomeEntity::class`, чтобы обратная сторона могла ссылаться
     на тот же pivotTable без ручного дублирования строк.

3) **Pivot данные + isolation при IdentityMap**
   - Pivot entity сейчас крепится прямо к target entity (`$role->pivot()`), и при шаринге entity между разными owner
     в рамках одного UnitOfWork pivot может перетираться.
   - Возможные улучшения:
     - хранить pivot в контексте relation-коллекции,
     - или возвращать wrapper (`RoleWithPivot`).

4) **Дополнительные relation helpers**
   - Расширить pivot helpers для кейсов:
     - обновление pivotData по map (`syncWithPivotData` уже есть),
     - точечный `updatePivot($relatedId, $pivotData)`,
     - batch attach/detach.

5) **Новые типы связей / sugar-API**
   - Возможные расширения (по мере необходимости проекта):
     - дополнительные варианты eager loading,
     - sugar-методы для common patterns.

## Оглавление

- [Quick Start](docs/01-quick-start.md)
- [Атрибуты и метаданные](docs/02-metadata-and-attributes.md)
- [Репозитории и EntityManager](docs/03-repositories-and-entity-manager.md)
- [TypeCasting](docs/04-typecasting.md)
- [Behaviors, события и DI](docs/05-behaviors-events-di.md)
- [Relations (связи)](docs/06-relations.md)
- [Pivot Entity](docs/07-pivot-entity.md)

## Quick Start

См. [docs/01-quick-start.md](docs/01-quick-start.md).
