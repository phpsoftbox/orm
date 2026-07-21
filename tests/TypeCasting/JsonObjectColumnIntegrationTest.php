<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\TypeCasting;

use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PhpSoftBox\Orm\Tests\TypeCasting\Fixtures\EntityItemMetadata;
use PhpSoftBox\Orm\Tests\TypeCasting\Fixtures\EntityMetadata;
use PhpSoftBox\Orm\Tests\TypeCasting\Fixtures\FbsEntityVariant;
use PhpSoftBox\Orm\Tests\TypeCasting\Fixtures\JsonObjectEntity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonObjectColumnIntegrationTest extends TestCase
{
    #[Test]
    public function infersDtoAndPassesSourceRowToFactoryWhileKeepingArrayMode(): void
    {
        $mapper = new AutoEntityMapper(
            new AttributeMetadataProvider(),
            new DefaultTypeCasterFactory()->create(),
            new TypeCastOptionsManager(),
        );

        $entity = $mapper->hydrate(JsonObjectEntity::class, [
            'id'            => 10,
            'metadata'      => '{"name":"shipment","nested":{"enabled":true}}',
            'items'         => '[{"code":"A"},{"code":"B"}]',
            'itemsByCode'   => '{"A":{"code":"A"}}',
            'plainPayload'  => '{"keep":"array"}',
            'metadata_type' => 'fbs',
            'variant'       => '{"code":"FBS-1"}',
        ]);

        self::assertInstanceOf(EntityMetadata::class, $entity->metadata);
        self::assertTrue($entity->metadata->nested?->enabled);
        self::assertContainsOnlyInstancesOf(EntityItemMetadata::class, $entity->items);
        self::assertInstanceOf(EntityItemMetadata::class, $entity->itemsByCode['A']);
        self::assertSame(['keep' => 'array'], $entity->plainPayload);
        self::assertInstanceOf(FbsEntityVariant::class, $entity->variant);

        $row = $mapper->extract($entity);

        self::assertSame('{"name":"shipment","nested":{"enabled":true}}', $row['metadata']);
        self::assertSame('[{"code":"A"},{"code":"B"}]', $row['items']);
        self::assertSame('{"A":{"code":"A"}}', $row['itemsByCode']);
        self::assertSame('{"keep":"array"}', $row['plainPayload']);
        self::assertSame('{"code":"FBS-1"}', $row['variant']);
    }

    #[Test]
    public function metadataExposesInferredJsonMapping(): void
    {
        $metadata = new AttributeMetadataProvider()->for(JsonObjectEntity::class);

        self::assertSame(EntityMetadata::class, $metadata->columns['metadata']->phpType);
        self::assertSame(EntityItemMetadata::class, $metadata->columns['items']->jsonCollectionItemClass);
        self::assertSame(EntityItemMetadata::class, $metadata->columns['itemsByCode']->jsonMapValueClass);
        self::assertNull($metadata->columns['plainPayload']->phpType);
        self::assertNotNull($metadata->columns['variant']->jsonFactoryClass);
    }
}
