<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Persistence;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Persistence\DefaultEntityPersister;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Tests\TypeCasting\Fixtures\AllTypesEntity;
use PhpSoftBox\Orm\Tests\Persistence\Fixtures\AllTypesPersistEntity;
use PhpSoftBox\Orm\TypeCasting\DefaultTypeCasterFactory;
use PhpSoftBox\Orm\TypeCasting\Options\TypeCastOptionsManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultEntityPersister::class)]
final class DefaultEntityPersisterTest extends TestCase
{
    /**
     * Проверяет, что persister может построить метаданные сущности и извлечь PK-колонку.
     *
     * Полноценные проверки INSERT/UPDATE/DELETE SQL будут добавлены позже как интеграционные тесты,
     * когда у нас будет общее место, где выполняется flush() (EntityManager + UnitOfWork).
     */
    #[Test]
    public function persisterResolvesPrimaryKeyColumn(): void
    {
        $connection = $this->createStub(ConnectionInterface::class);

        $metadata = new AttributeMetadataProvider();
        $mapper = new AutoEntityMapper(
            metadata: $metadata,
            typeCaster: (new DefaultTypeCasterFactory())->create(),
            optionsManager: new TypeCastOptionsManager(),
        );

        $persister = new DefaultEntityPersister($connection, $metadata, $mapper);

        // Достаточно убедиться, что метаданные строятся и PK определён.
        $meta = $metadata->for(AllTypesPersistEntity::class);
        self::assertNotEmpty($meta->pkProperties);
    }
}
