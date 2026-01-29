# Quick Start

## Установка

```bash
composer require phpsoftbox/orm
```

## 1) Подключение к БД (DBAL)

ORM работает поверх `phpsoftbox/database`.

Вы создаёте `ConnectionInterface` (или `Database`) любым удобным способом и передаёте подключение в `EntityManager`.

## 2) Сущность (Entity)

Пример сущности с атрибутами метаданных:

```php
<?php

declare(strict_types=1);

use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\NotMapped;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'users')]
final class User implements EntityInterface
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'uuid')]
        #[Column(type: 'uuid')]
        public readonly UuidInterface $id,

        #[Column(type: 'string', length: 255)]
        public string $name,

        #[NotMapped]
        public ?string $computed = null,
    ) {}

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}
```

> Совет: если вы не указываете `table` в `#[Entity]`, то ORM попытается вывести имя таблицы по конвенции
> (через Inflector и NamingConvention). Для предсказуемости в продакшене часто задают `table` явно.

## 3) Репозиторий

Репозиторий отвечает за работу с БД и маппинг сущности.

На текущем этапе репозиторий можно реализовать вручную (через `AbstractEntityRepository`).

## 4) EntityManager

### Вариант A: авто-резолв репозитория (предпочтительно)

Если репозиторий не зарегистрирован вручную, `EntityManager` попробует его создать автоматически.

Соглашение по умолчанию:

- `App\Entity\User` → `App\Entity\Repository\UserRepository`

```php
<?php

use PhpSoftBox\Orm\EntityManager;

$em = new EntityManager($connection);

$repo = $em->repository(User::class);
$user = $repo->find($uuid);
```

### Вариант B: регистрация репозитория вручную

```php
<?php

use PhpSoftBox\Orm\EntityManager;

$em = new EntityManager($connection);
$em->registerRepository(User::class, new UserRepository($connection));

$user = $em->repository(User::class)->find($uuid);
```
