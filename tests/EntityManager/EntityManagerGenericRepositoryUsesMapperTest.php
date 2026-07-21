<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\MarkerEntity;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\MarkerTypeHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class EntityManagerGenericRepositoryUsesMapperTest extends TestCase
{
    /**
     * Проверяет, что GenericEntityRepository использует mapper из EntityManager.
     */
    #[Test]
    public function genericRepositoryUsesEntityManagerMapper(): void
    {
        $connection = $this->createStub(ConnectionInterface::class);

        $metadata = new AttributeMetadataProvider();
        $caster   = new DefaultTypeCasterFactory()->create();

        $caster->registerHandler(new MarkerTypeHandler());

        $mapper = new AutoEntityMapper(
            metadata: $metadata,
            typeCaster: $caster,
            optionsManager: new TypeCastOptionsManager(),
        );

        $em = new EntityManager($connection, mapper: $mapper);

        $repo = $em->bulkRepository(MarkerEntity::class);

        $entities = $repo->hydrateManyRows([
            ['id' => 1, 'marker' => 'hello'],
        ]);

        $entity = $entities->first();

        self::assertInstanceOf(MarkerEntity::class, $entity);
        self::assertSame('mapped:hello', $entity->marker);
    }
}
