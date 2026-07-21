<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Persistence\DefaultEntityPersister;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\AutoIdEntity;
use PhpSoftBox\Orm\Tests\Utils\IntegrationDatabases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultEntityPersister::class)]
final class EntityManagerAutoIdTest extends TestCase
{
    /**
     * Проверяет, что auto-идентификатор проставляется на сущность после insert.
     */
    #[Test]
    public function assignsAutoIdAfterPersist(): void
    {
        $db         = IntegrationDatabases::sqliteDatabase();
        $connection = $db->connection();

        $connection->schema()->create('auto_entities', function (TableBlueprint $table): void {
            $table->id();
            $table->string('name', 100);
        });

        $em = new EntityManager($connection);

        $entity = new AutoIdEntity(name: 'Test');

        $em->persist($entity);
        $em->flush();

        $this->assertNotNull($entity->id);
        $this->assertGreaterThan(0, $entity->id);

        $row = $connection->fetchOne('SELECT * FROM auto_entities LIMIT 1');

        $this->assertNotNull($row);
        $this->assertSame($entity->id, (int) $row['id']);
    }
}
