<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Exception\RepositoryNotRegisteredException;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Persistence\EntityPersisterInterface;
use PhpSoftBox\Orm\Repository\DefaultRepositoryResolver;
use PhpSoftBox\Orm\Repository\RepositoryClassFactory;
use PhpSoftBox\Orm\Tests\Fixtures\App\Entity\UserEntity;
use PhpSoftBox\Orm\Tests\Fixtures\App\Repository\UserEntityRepository;
use PhpSoftBox\Orm\Tests\Fixtures\NoEntityAttribute;
use PhpSoftBox\Orm\Tests\Fixtures\Repository\UserRepository;
use PhpSoftBox\Orm\Tests\Fixtures\SpyExistingRepository;
use PhpSoftBox\Orm\Tests\Fixtures\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(EntityManager::class)]
final class EntityManagerTest extends TestCase
{
    /**
     * Проверяет, что EntityManager возвращает ровно то DBAL-подключение, которое было передано в конструктор.
     */
    #[Test]
    public function returnsConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $em = new EntityManager($connection);

        self::assertSame($connection, $em->connection());
    }

    /**
     * Проверяет, что EntityManager умеет автоматически резолвить репозиторий по соглашению:
     * EntityNamespace\\Repository\\{EntityName}Repository.
     */
    #[Test]
    public function autoResolvesRepositoryByConvention(): void
    {
        $em = new EntityManager($this->createMock(ConnectionInterface::class));

        $repo = $em->repository(User::class);

        self::assertInstanceOf(UserRepository::class, $repo);
    }

    /**
     * Проверяет, что flush() вызывает persist/remove на зарегистрированном репозитории и очищает очереди.
     */
    #[Test]
    public function flushCallsRegisteredRepositories(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('transaction')->willReturnCallback(static fn (callable $fn) => $fn());

        $persister = $this->createMock(EntityPersisterInterface::class);
        $persister->expects(self::never())->method('insert');
        $persister->expects(self::once())->method('update');
        $persister->expects(self::once())->method('delete');

        $em = new EntityManager($connection, persister: $persister);

        $em->registerRepository(User::class, new SpyExistingRepository());

        $user1 = new User(Uuid::uuid7(), 'u1');
        $user2 = new User(Uuid::uuid7(), 'u2');

        $em->persist($user1);
        $em->persist($user2);
        $em->remove($user1);

        $em->flush();

        // Повторный flush() не должен вызывать persister, т.к. scheduled operations уже очищены.
        $em->flush();
    }

    /**
     * Проверяет, что если сущность не имеет атрибута #[Entity] и репозиторий не зарегистрирован,
     * то EntityManager не сможет сделать auto-resolve и выбросит исключение.
     */
    #[Test]
    public function throwsWhenEntityHasNoEntityAttribute(): void
    {
        $em = new EntityManager($this->createMock(ConnectionInterface::class));

        $this->expectException(RepositoryNotRegisteredException::class);

        $em->repository(NoEntityAttribute::class);
    }

    /**
     * Проверяет, что DefaultRepositoryResolver можно сконфигурировать через DI,
     * задав список namespace'ов для поиска репозиториев (defaultRepositoryNamespaces).
     */
    #[Test]
    public function autoResolvesRepositoryUsingDefaultRepositoryNamespace(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $resolver = new DefaultRepositoryResolver([
            'PhpSoftBox\\Orm\\Tests\\Fixtures\\App\\Repository',
        ]);

        $factory = new RepositoryClassFactory(
            metadata: new AttributeMetadataProvider(),
            resolver: $resolver,
        );

        $em = new EntityManager($connection, repositoryFactory: $factory);

        $repo = $em->repository(UserEntity::class);

        self::assertInstanceOf(UserEntityRepository::class, $repo);
    }
}
