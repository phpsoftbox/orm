<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use PhpSoftBox\Orm\Metadata\ClassMetadata;

interface RepositoryResolverInterface
{
    /**
     * Список namespace'ов, в которых можно искать репозитории по умолчанию.
     *
     * Пример: ['App\\Repository', 'App\\ReadRepository'].
     *
     * По умолчанию - пустой массив.
     *
     * @return list<string>
     */
    public function defaultRepositoryNamespaces(): array;

    /**
     * Определяет класс репозитория для сущности.
     *
     * Стратегии (по умолчанию):
     * 1) Явно указанный класс в #[Entity(repository: ...)]
     * 2) Поиск в defaultRepositoryNamespaces(): {Ns}\\{EntityShortName}Repository
     * 3) Поиск по соглашению: {EntityNamespace}\\{repositoryNamespace}\\{EntityShortName}Repository
     * 4) Fallback на generic repository
     *
     * @param class-string $entityClass
     * @return class-string<RepositoryInterface>
     */
    public function resolve(string $entityClass, ?ClassMetadata $meta = null): string;
}
