<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Repository;

use InvalidArgumentException;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\RepositoryFactoryInterface;
use PhpSoftBox\Orm\Contracts\RepositoryInterface;
use PhpSoftBox\Orm\Contracts\RepositoryResolverInterface;
use PhpSoftBox\Orm\Exception\RepositoryNotRegisteredException;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use ReflectionClass;

use function is_a;

/**
 * Фабрика репозиториев, которая:
 * - читает метаданные сущности,
 * - резолвит класс репозитория через RepositoryResolverInterface,
 * - создаёт инстанс репозитория.
 *
 * Важно: пока создаём репозитории напрямую через new ($connection) без DI,
 * чтобы сохранить небольшой surface area. Далее можно заменить на DI-aware factory.
 */
final readonly class RepositoryClassFactory implements RepositoryFactoryInterface
{
    public function __construct(
        private MetadataProviderInterface $metadata,
        private RepositoryResolverInterface $resolver,
    ) {
    }

    public function create(string $entityClass, EntityManagerInterface $em): RepositoryInterface
    {
        try {
            $meta = $this->metadata->for($entityClass);
        } catch (InvalidArgumentException $e) {
            throw new RepositoryNotRegisteredException($e->getMessage(), 0, $e);
        }

        $repoClass = $this->resolver->resolve($entityClass, $meta);

        if (!is_a($repoClass, RepositoryInterface::class, true)) {
            throw new RepositoryNotRegisteredException('Resolved repository class does not implement RepositoryInterface: ' . $repoClass);
        }

        /** @var class-string<RepositoryInterface> $repoClass */
        if ($repoClass === GenericEntityRepository::class) {
            return new $repoClass($em->connection(), $entityClass);
        }

        $rc = new ReflectionClass($repoClass);

        $ctor     = $rc->getConstructor();
        $ctorArgs = $ctor?->getNumberOfParameters() ?? 0;

        // 1) legacy: repo(connection)
        if ($ctorArgs <= 1) {
            return new $repoClass($em->connection());
        }

        // 2) new style: repo(connection, em)
        return new $repoClass($em->connection(), $em);
    }
}
