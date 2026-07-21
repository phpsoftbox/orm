# Full-Text Search

ORM использует PostgreSQL full-text API из `phpsoftbox/database` и добавляет entity-aware обертки.

## EntityResult

`rank`, `headline`, агрегаты и другие вычисленные колонки не записываются в entity. Для таких запросов используется `EntityResult`:

```php
$results = $em->queryFor(Product::class)
    ->selectPgFullTextRank('search_vector', $query)
    ->selectPgFullTextHeadline('description', $query)
    ->wherePgFullText('search_vector', $query)
    ->orderByPgFullTextRank()
    ->fetchEntityResults();

foreach ($results as $result) {
    $product = $result->entity;
    $rank = $result->extra('search_rank');
    $headline = $result->extra('search_headline');
}
```

Пагинационный вариант:

```php
$page = $em->queryFor(Product::class)
    ->selectPgFullTextRank('search_vector', $query)
    ->wherePgFullText('search_vector', $query)
    ->paginateEntityResults();
```

## Explicit API

```php
use PhpSoftBox\Database\Postgres\FullText\PgFullTextOptions;

$products = $em->queryFor(Product::class)
    ->wherePgFullText('search_vector', $query, new PgFullTextOptions(config: 'russian'))
    ->selectPgFullTextRank('search_vector', $query)
    ->selectPgFullTextHeadline('description', $query)
    ->orderByPgFullTextRank()
    ->fetchEntityResults();
```

Если передано имя свойства entity, ORM резолвит его в физическую колонку. Если передано имя технической колонки, которой нет в entity, ORM использует его как колонку таблицы.

## Search Profiles

Если у entity несколько векторных колонок, опишите их class-level атрибутами:

```php
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\FullTextSearch;

#[Entity(table: 'products')]
#[FullTextSearch(
    name: 'natural',
    vectorColumn: 'natural_search_vector',
    config: 'russian',
    default: true,
)]
#[FullTextSearch(
    name: 'technical',
    vectorColumn: 'technical_search_vector',
    config: 'simple',
)]
final class Product
{
}
```

Запрос по default profile:

```php
$results = $em->queryFor(Product::class)
    ->whereSearch($query)
    ->selectSearchRank($query)
    ->selectSearchHeadline('description', $query)
    ->orderByPgFullTextRank()
    ->fetchEntityResults();
```

Запрос по конкретному profile:

```php
$results = $em->queryFor(Product::class)
    ->whereSearch($query, profile: 'technical')
    ->selectSearchRank($query, profile: 'technical')
    ->fetchEntityResults();
```

Поиск сразу по нескольким profile:

```php
$results = $em->queryFor(Product::class)
    ->whereAnySearch($query, ['natural', 'technical'])
    ->fetchEntities();
```

Если у entity несколько profiles и ни один не помечен `default: true`, вызов `whereSearch($query)` без имени profile завершится ошибкой неоднозначности.

## Computed Aggregate Columns

Для агрегатов рядом с entity используйте `fetchEntityResults()`:

```php
$results = $em->queryFor(Product::class)
    ->selectCount('id', 'products_count')
    ->groupBy('id')
    ->fetchEntityResults();

$count = $results[0]->extra('products_count');
```

Terminal-методы `count()`, `sum()`, `avg()`, `min()`, `max()` остаются скалярными и не возвращают entity.
