<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Repository;

use PhpSoftBox\Orm\Contracts\RepositoryInterface;
use PhpSoftBox\Orm\Contracts\RepositoryResolverInterface;
use PhpSoftBox\Orm\Metadata\ClassMetadata;

use function class_exists;
use function strrpos;
use function substr;

final readonly class DefaultRepositoryResolver implements RepositoryResolverInterface
{
    /**
     * @param list<string> $defaultRepositoryNamespaces
     */
    public function __construct(
        private array $defaultRepositoryNamespaces = [],
    ) {
    }

    public function defaultRepositoryNamespaces(): array
    {
        return $this->defaultRepositoryNamespaces;
    }

    public function resolve(string $entityClass, ?ClassMetadata $meta = null): string
    {
        // 1) explicit repository from metadata
        if ($meta?->repository !== null) {
            /** @var class-string<RepositoryInterface> $repoClass */
            $repoClass = $meta->repository;

            return $repoClass;
        }

        $short = $this->shortName($entityClass);

        // 2) search in default namespaces
        foreach ($this->defaultRepositoryNamespaces as $ns) {
            $candidate = $ns . '\\' . $short . 'Repository';
            if (class_exists($candidate)) {
                /** @var class-string<RepositoryInterface> $candidate */
                return $candidate;
            }
        }

        // 3) entityNamespace + repositoryNamespace
        $entityNs = $this->namespace($entityClass);
        if ($meta !== null && $entityNs !== null) {
            $candidate = $entityNs . '\\' . $meta->repositoryNamespace . '\\' . $short . 'Repository';
            if (class_exists($candidate)) {
                /** @var class-string<RepositoryInterface> $candidate */
                return $candidate;
            }
        }

        // 4) fallback generic
        return GenericEntityRepository::class;
    }

    private function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    private function namespace(string $class): ?string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? null : substr($class, 0, $pos);
    }
}
